//
// Copyright Aliaksei Levin (levlam@telegram.org), Arseny Smirnov (arseny30@gmail.com) 2014-2017
//
// Distributed under the Boost Software License, Version 1.0. (See accompanying
// file LICENSE_1_0.txt or copy at http://www.boost.org/LICENSE_1_0.txt)
//
#pragma once

#include "td/actor/actor.h"

#include "td/actor/PromiseFuture.h"

namespace td {

class SleepActor : public Actor {
 public:
  SleepActor(double timeout, Promise<> promise) : timeout_(timeout), promise_(std::move(promise)) {
  }

 private:
  double timeout_;
  Promise<> promise_;

  void start_up() override {
    set_timeout_in(timeout_);
  }
  void timeout_expired() override {
    promise_.set_value(Unit());
  }
};

}  // namespace td
