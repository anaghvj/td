//
// Copyright Aliaksei Levin (levlam@telegram.org), Arseny Smirnov (arseny30@gmail.com) 2014-2017
//
// Distributed under the Boost Software License, Version 1.0. (See accompanying
// file LICENSE_1_0.txt or copy at http://www.boost.org/LICENSE_1_0.txt)
//
#pragma once

#include "td/actor/actor.h"
#include "td/actor/PromiseFuture.h"

#include "td/utils/BufferedFd.h"
#include "td/utils/common.h"
#include "td/utils/port/IPAddress.h"
#include "td/utils/port/SocketFd.h"
#include "td/utils/Status.h"

namespace td {

class Socks5 : public Actor {
 public:
  class Callback {
   public:
    Callback() = default;
    Callback(const Callback &) = delete;
    Callback &operator=(const Callback &) = delete;
    virtual ~Callback() = default;

    virtual void set_result(Result<SocketFd>) = 0;
    virtual void on_connected() = 0;
  };

  Socks5(SocketFd socket_fd, IPAddress ip_address, string username, string password, std::unique_ptr<Callback> callback,
         ActorShared<> parent);

 private:
  BufferedFd<SocketFd> fd_;
  IPAddress ip_address_;
  string username_;
  string password_;
  std::unique_ptr<Callback> callback_;
  ActorShared<> parent_;

  void on_error(Status status);
  void tear_down() override;
  void start_up() override;
  void hangup() override;

  enum class State {
    SendGreeting,
    WaitGreetingResponse,
    WaitPasswordResponse,
    SendIpAddress,
    WaitIpAddressResponse,
    Stop
  } state_ = State::SendGreeting;

  void send_greeting();
  Status wait_greeting_response();
  Status send_username_password();

  Status wait_password_response();

  void send_ip_address();
  Status wait_ip_address_response();

  void loop() override;
  void timeout_expired() override;
};

}  // namespace td
