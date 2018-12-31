//
// Copyright Aliaksei Levin (levlam@telegram.org), Arseny Smirnov (arseny30@gmail.com) 2014-2017
//
// Distributed under the Boost Software License, Version 1.0. (See accompanying
// file LICENSE_1_0.txt or copy at http://www.boost.org/LICENSE_1_0.txt)
//
#include "td/telegram/DialogId.h"

#include "td/telegram/telegram_api.h"

#include "td/utils/logging.h"

namespace td {

bool DialogId::is_valid() const {
  switch (get_type()) {
    case DialogType::User: {
      return get_user_id().is_valid();
    }
    case DialogType::Chat: {
      return get_chat_id().is_valid();
    }
    case DialogType::Channel: {
      return get_channel_id().is_valid();
    }
    case DialogType::SecretChat: {
      return get_secret_chat_id().is_valid();
    }
    case DialogType::None:
      return false;
    default:
      UNREACHABLE();
      return false;
  }
}

DialogType DialogId::get_type() const {
  if (id < 0) {
    if (MIN_CHAT_ID <= id) {
      return DialogType::Chat;
    }
    if (MIN_CHANNEL_ID <= id && id < MAX_CHANNEL_ID) {
      return DialogType::Channel;
    }
    if (MIN_SECRET_ID <= id && id < MAX_SECRET_ID) {
      return DialogType::SecretChat;
    }
  } else if (0 < id && id <= MAX_USER_ID) {
    return DialogType::User;
  }
  return DialogType::None;
}

UserId DialogId::get_user_id() const {
  CHECK(get_type() == DialogType::User);
  return UserId(static_cast<int32>(id));
}

ChatId DialogId::get_chat_id() const {
  CHECK(get_type() == DialogType::Chat);
  return ChatId(static_cast<int32>(-id));
}

SecretChatId DialogId::get_secret_chat_id() const {
  CHECK(get_type() == DialogType::SecretChat);
  return SecretChatId(static_cast<int32>(id - ZERO_SECRET_ID));
}

ChannelId DialogId::get_channel_id() const {
  CHECK(get_type() == DialogType::Channel);
  return ChannelId(static_cast<int32>(MAX_CHANNEL_ID - id));
}

DialogId::DialogId(UserId user_id) {
  if (user_id.is_valid()) {
    id = static_cast<int64>(user_id.get());
  } else {
    id = 0;
  }
}

DialogId::DialogId(ChatId chat_id) {
  if (chat_id.is_valid()) {
    id = -static_cast<int64>(chat_id.get());
  } else {
    id = 0;
  }
}

DialogId::DialogId(ChannelId channel_id) {
  if (channel_id.is_valid()) {
    id = MAX_CHANNEL_ID - static_cast<int64>(channel_id.get());
  } else {
    id = 0;
  }
}

DialogId::DialogId(SecretChatId chat_id) {
  if (chat_id.is_valid()) {
    id = ZERO_SECRET_ID + static_cast<int64>(chat_id.get());
  } else {
    id = 0;
  }
}

DialogId::DialogId(const tl_object_ptr<telegram_api::Peer> &peer) {
  CHECK(peer != nullptr);

  int constructor_id = peer->get_id();
  switch (constructor_id) {
    case telegram_api::peerUser::ID: {
      auto peer_user = static_cast<const telegram_api::peerUser *>(peer.get());
      UserId user_id(peer_user->user_id_);
      if (!user_id.is_valid()) {
        LOG(ERROR) << "Receive invalid " << user_id;
        id = 0;
      } else {
        id = static_cast<int64>(user_id.get());
      }
      return;
    }
    case telegram_api::peerChat::ID: {
      auto peer_chat = static_cast<const telegram_api::peerChat *>(peer.get());
      ChatId chat_id(peer_chat->chat_id_);
      if (!chat_id.is_valid()) {
        LOG(ERROR) << "Receive invalid " << chat_id;
        id = 0;
      } else {
        id = -static_cast<int64>(chat_id.get());
      }
      return;
    }
    case telegram_api::peerChannel::ID: {
      auto peer_channel = static_cast<const telegram_api::peerChannel *>(peer.get());
      ChannelId channel_id(peer_channel->channel_id_);
      if (!channel_id.is_valid()) {
        LOG(ERROR) << "Receive invalid " << channel_id;
        id = 0;
      } else {
        id = MAX_CHANNEL_ID - static_cast<int64>(channel_id.get());
      }
      return;
    }
    default:
      UNREACHABLE();
  }
  id = 0;
}

}  // namespace td
