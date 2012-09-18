(function ($) {
    "use strict";

    var Chat = function (nodejs, settings) {
        this.nodejs = nodejs;
        this.settings = {
            container:'body',
            tpl:{
                status:'',
                user:'',
                window:'',
                message:'',
                messages:'',
                chat:''
            },
            url:{
                cache:'',
                activate:'',
                close:'',
                send:'',
                ping:''
            }
        };
        $.extend(this.settings, settings);
        this.status = false;
    };

    Chat.prototype.isOpen = function (uid) {
        return ($.inArray(uid, this.data.v) !== -1);
    };

    Chat.prototype.addOpen = function (uid) {
        this.data.v.push(uid);
    };

    Chat.prototype.isActive = function (uid) {
        return (this.data.a === uid);
    };

    Chat.prototype.setActive = function (uid) {
        this.data.a = uid;
    };

    Chat.prototype.removeActive = function (uid) {
        var index = $.inArray(uid, this.data.v);
        this.data.v.splice(index, index + 1);
    };

    Chat.prototype.getActive = function () {
        return this.data.a;
    };

    Chat.prototype.isStatusWindowOpen = function () {
        return this.status;
    };

    Chat.prototype.setStatusWindowOpen = function (state) {
        this.status = state;
    };

    Chat.prototype.chatWith = function (uid, name, picture) {
        uid = window.parseInt(uid);
        if (!this.isOpen(uid)) {
            var partner = {
                u:uid,
                n:name,
                p:picture
            };
            this.addOpen(uid);
            this.localCreateWindow(partner);
        }
        if (!this.isActive(uid)) {
            this.localDeactivateWindow(this.getActive());
            this.setActive(uid);
            this.localActivateWindow(uid);
            this.remoteActivateWindow(uid);
        }
    };

    Chat.prototype.chatToggle = function (uid) {
        if (this.isNumber(uid)) {
            uid = window.parseInt(uid);
            if (this.isActive(uid)) {
                // User window has been deactivated.
                this.setActive(0);
                this.localDeactivateWindow(uid);
                this.remoteDeactivateWindow(uid);
            } else {
                // User window has been activated.
                // Deactivate the old window.
                this.localDeactivateWindow(this.getActive());
                this.setActive(uid);
                this.localActivateWindow(uid);
                this.remoteActivateWindow(uid);
            }
        } else {
            if (this.isStatusWindowOpen()) {
                this.setStatusWindowOpen(false);
                this.localDeactivateStatus();
            } else {
                this.setStatusWindowOpen(true);
                this.localActivateStatus();
            }
        }
    };

    Chat.prototype.chatMinimize = function (uid) {
        this.localDeactivateWindow(uid);
        if (this.isNumber(uid)) {
            this.setActive(0);
            this.remoteDeactivateWindow(uid);
        } else {
            this.setStatusWindowOpen(false);
        }
    };

    Chat.prototype.chatClose = function (uid) {
        // If this is currently active window, unset the active window.
        if (this.isActive(uid)) {
            this.setActive(0);
        }
        this.removeActive(uid);
        this.localCloseWindow(uid);
        this.remoteCloseWindow(uid);
    };

    Chat.prototype.chatSend = function (uid, name, picture, messageText) {
        var partner = {
            u:uid,
            n:name,
            p:picture
        };
        var message = {
            i:0,
            t:new Date().getTime() / 1000,
            b:this.escape(messageText),
            r:false
        };
        this.localSendMessage(partner, message);
        this.remoteSendMessage(uid, messageText);
    };

    Chat.prototype.chatFocus = function (uid) {
        $('[data-chat="input"][data-uid="' + uid + '"]', this.context).focus();
    };

    Chat.prototype.getHeight = function (id, elementClass, text) {
        var cloneId = 'chat-clone-' + this.sid + '-' + id;
        var $clone = $('#' + cloneId);
        if (!$clone.length) {
            $clone = $('<div />').attr('id', cloneId).addClass(elementClass).css({
                maxHeight:'none',
                position:'absolute',
                wordWrap:'break-word',
                height:'auto',
                display:'none'
            });
            this.context.append($clone);
        }
        $clone.html(text
            .replace(/&/g, '&amp;')
            .replace(/ {2}/g, ' &nbsp;').replace(/<|>/g, '&gt;')
            .replace(/\n/g, '<br />') +
            '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;');
        return $clone.height();
    };

    Chat.prototype.initialize = function () {
        var chat = this;
        chat.context = $(chat.compileTpl('chat'));
        var container = $(chat.settings.container);
        container.append(chat.context);

        $(chat.context).on('click', '[data-chat="toggle"]', function () {
            var $context = $(this);
            var uid = $context.data('uid');
            chat.chatToggle(uid);
            // Stop click propagation.
            return false;
        });

        $(chat.context).on('click', '[data-chat="user"]', function () {
            var $context = $(this);
            chat.chatWith($context.data('uid'), $context.data('name'), $context.data('picture'));
            // Stop click propagation.
            return false;
        });

        $(this.context).on('click', '[data-chat="minimize"]', function (e) {
            var $context = $(e.srcElement);
            if ($context.data('chat') === 'minimize') {
                var uid = $context.data('uid');
                chat.chatMinimize(uid);
            }
            // Stop click propagation.
            return false;
        });

        $(this.context).on('click', '[data-chat="close"]', function () {
            var $context = $(this);
            var uid = window.parseInt($context.data('uid'));
            chat.chatClose(uid);
            // Stop click propagation.
            return false;
        });

        $(chat.context).on('click', '[data-chat="focus"]', function () {
            var $context = $(this);
            var uid = window.parseInt($context.data('uid'));
            chat.chatFocus(uid);
        });

        $(chat.context).on('keyup', '[data-chat="input"]', function () {
            var $context = $(this);
            var uid = $context.data('uid');
            $context.css('height', chat.getHeight($context.data('uid'), $context.attr('class'), $context.val()));
        });

        $(chat.context).on('keydown', '[data-chat="input"]', function (e) {
            if (e.keyCode === 13) {
                var $context = $(this);
                chat.chatSend($context.data('uid'), $context.data('name'), $context.data('picture'), $context.val());
                $context.val('');
                // Stop event propagation.
                e.preventDefault();
            }
        });

        $.post(chat.settings.url.cache, {token:chat.nodejs.authToken}, function (data) {
            chat.sid = chat.nodejs.socketId;
            chat.data = data;
            chat.state = {};
            chat.pong = 0;
            chat.pingInterval = setInterval(function () {
                var now = window.parseInt(new Date().getTime() / 1000);
                if ((now - chat.pong) >= 300) {
                    $.post(chat.settings.url.ping, {token:chat.nodejs.authToken});
                }
            }, 303 * 1000);
            chat.user = {
                u:data.u,
                n:data.n,
                p:data.p
            };
            // First generate the status block
            var onlineUsersHtml = '';

            // Iterate through all online users
            var countUsers = 0;
            for (var partnerUid in chat.data.o) {
                if (chat.data.o.hasOwnProperty(partnerUid)) {
                    var onlineUser = chat.data.o[partnerUid];
                    // n:name, s:status, p:picture, u:uid
                    var userData = {
                        uid:onlineUser.u,
                        name:onlineUser.n,
                        status:onlineUser.s,
                        picture:onlineUser.p
                    };
                    onlineUsersHtml += chat.compileTpl('user', userData);
                    countUsers++;
                }
            }
            var onlineUsersData = {
                users:onlineUsersHtml,
                count:countUsers
            };
            onlineUsersHtml = chat.compileTpl('status', onlineUsersData);
            $('[data-chat="placeholder"]', chat.context).replaceWith(onlineUsersHtml);

            // chat.data.v is an array that stores all open window's uid's in user's session.
            // Iterate through all open chat windows.
            for (var i = 0; i < chat.data.v.length; i++) {
                var uid = data.v[i];
                // This is the chat window we're currently working with
                var chatWindow = data.w[uid];
                // User's chat partner in this window
                var partner = chatWindow.d;
                // Insert our chat window in DOM at this point
                chat.localCreateWindow(partner);
                for (var j in chatWindow.m) {
                    // Iterate through all messages in this window
                    var message = chatWindow.m[j];
                    chat.localSendMessage(partner, message);
                }
                if (chatWindow.e) {
                    chat.newMessages(uid, chatWindow.e);
                }
            }

            if (chat.getActive()) {
                chat.localActivateWindow(chat.getActive());
            }

            chat.nodejs.callbacks['chat_' + chat.user.u] = function (message) {
                // Each request made by the chat sends a socket ID, unique to every open tab,
                // which is broadcast with every socket message sent from server.
                // If those IDs match, it means the request originated from
                // this tab. This is done so no duplicate operations occur.
                var isOrigin = (message.data.sid === chat.sid);
                // These commands reflect user's actions, such as open (window), close,
                // activate, etc.
                switch (message.data.command) {
                    case 'pong':
                        chat.pong = window.parseInt(new Date().getTime() / 1000);
                        break;

                    case 'message':
                        if (message.data.receiver.u === chat.user.u) {
                            // Received message.
                            message.data.message.r = true;
                            // Is this a new window?
                            if ($.inArray(message.data.sender.u, chat.data.v) === -1) {
                                // Is there an active window already or is this window already open?
                                if (chat.data.a === 0 && $.inArray(message.data.sender.u, chat.data.v) === -1) {
                                    // Not among open windows and there are no open windows, open and activate this one, but don't focus it.
                                    chat.localCreateWindow(message.data.sender);
                                    chat.localActivateWindow(message.data.sender.u);
                                    chat.data.a = message.data.sender.u;
                                    chat.remoteActivateWindow(message.data.sender.u);
                                } else {
                                    // New window, open it, but the user is busy, open it in background.
                                    chat.localCreateWindow(message.data.sender);
                                }
                                chat.data.v.push(message.data.sender.u);
                            }
                            // If this chat window is scrolled to the bottom we should scroll it again after we append the message.
                            var senderScrolledToBottom = chat.isScrolledToBottom(message.data.sender.u);
                            chat.localSendMessage(message.data.sender, message.data.message);
                            if (senderScrolledToBottom) {
                                chat.scrollToBottom(message.data.sender.u);
                            }
                            // The window that we appended messages to wasn't active, meaning that we should notify the user of new message(s).
                            if (chat.data.a !== message.data.sender.u) {
                                chat.newMessages(message.data.sender.u);
                            }
                        } else {
                            // Sent message.
                            message.data.message.r = false;
                            // Is this the tab that the user sent the message from?
                            if (!isOrigin) {
                                // Message is sent from another tab, just append it in the chat.
                                var receiverScrolledToBottom = chat.isScrolledToBottom(message.data.receiver.u);
                                chat.localSendMessage(message.data.receiver, message.data.message);
                                if (receiverScrolledToBottom) {
                                    chat.scrollToBottom(message.data.receiver.u);
                                }
                            } else {
                                // Message is sent from this tab, and is already in the window, but without an ID, so set it now.
                                // Modules can also alter messages, such as converting YouTube links to embed codes, we should alter the message at this point.
                                chat.localUpdateMessage(message.data.sender.u, message.data.message.i, message.data.message.b);
                            }
                        }
                        break;

                    case 'close':
                        if (!isOrigin) {
                            chat.localCloseWindow(message.data.uid);
                            var closeIndex = $.inArray(message.data.uid, chat.data.v);
                            chat.data.v.splice(closeIndex, closeIndex + 1);
                            if (chat.data.a === message.data.uid) {
                                chat.data.a = 0;
                            }
                        }
                        break;

                    case 'activate':
                        if (!isOrigin) {
                            if (message.data.uid === 0) {
                                chat.localDeactivateWindow(chat.data.a);
                            } else {
                                var index = $.inArray(message.data.uid, chat.data.v);
                                if (index === -1) {
                                    chat.data.v.push(message.data.uid);
                                    chat.localCreateWindow(message.data.d);
                                }
                                chat.localActivateWindow(message.data.uid);
                                chat.scrollToBottom(message.data.uid);
                                chat.noNewMessages(message.data.uid);
                            }
                            chat.data.a = message.data.uid;
                        }
                        break;
                }
            };
        });
    };

    Chat.prototype.isNumber = function (n) {
        return !isNaN(parseFloat(n)) && isFinite(n);
    };

    Chat.prototype.newMessages = function (uid, count) {
        $('[data-chat="window"][data-uid="' + uid + '"]', this.context).addClass('new-messages');
    };

    Chat.prototype.noNewMessages = function (uid) {
        $('[data-chat="window"][data-uid="' + uid + '"]', this.context).removeClass('new-messages');
    };

    Chat.prototype.escape = function (text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    };

    Chat.prototype.isScrolledToBottom = function (uid) {
        if (this.data.a !== uid) {
            // This chat window is not open at all.
            return false;
        }
        var conversation = $('[data-chat="focus"][data-uid="' + uid + '"]');
        return (conversation.prop('scrollHeight') - conversation.scrollTop()) === conversation.height();
    };

    Chat.prototype.scrollToBottom = function (uid) {
        // Scroll this chat window to the bottom.
        var conversation = $('[data-chat="focus"][data-uid="' + uid + '"]');
        conversation.scrollTop(conversation.prop('scrollHeight') - conversation.height());
    };

    Chat.prototype.compileTpl = function (tplName, variables) {
        var tpl = this.settings.tpl[tplName];
        variables = variables || {};
        for (var variableName in variables) {
            if (variables.hasOwnProperty(variableName)) {
                tpl = tpl.replace(new RegExp('\\$\{' + variableName + '\}', 'gm'), variables[variableName]);
            }
        }
        return tpl;
    };

    Chat.prototype.generateWindow = function (partner, messages) {
        messages = messages || '';
        var windowData = {
            uid:partner.u,
            name:partner.n,
            picture:partner.p,
            messages:messages
        };
        return this.compileTpl('window', windowData);
    };

    Chat.prototype.localDeactivateWindow = function (uid) {
        $('[data-chat="window"][data-uid="' + uid + '"]', this.context).removeClass('active');
        $('[data-chat="panel"][data-uid="' + uid + '"]', this.context).hide();
    };

    Chat.prototype.localCreateWindow = function (partner) {
        this.state[partner.u] = {
            last:false
        };
        var chatWindow = $(this.generateWindow(partner));
        $('[data-chat="windows"]', this.context).prepend(chatWindow);
    };

    Chat.prototype.localActivateStatus = function () {
        $('[data-chat="window"][data-uid="status"]', this.context).addClass('active');
        $('[data-chat="panel"][data-uid="status"]', this.context).show();
    };

    Chat.prototype.localDeactivateStatus = function () {
        $('[data-chat="window"][data-uid="status"]', this.context).removeClass('active');
        $('[data-chat="panel"][data-uid="status"]', this.context).hide();
    };

    Chat.prototype.localActivateWindow = function (uid) {
        for (var i = 0; i < this.data.v.length; i++) {
            //if (this.data.v[i] !== uid) {
            if (this.data.v[i] !== uid) {
                this.localDeactivateWindow(uid);
            }
        }
        $('[data-chat="window"][data-uid="' + uid + '"]', this.context).addClass('active');
        $('[data-chat="panel"][data-uid="' + uid + '"]', this.context).show();
        this.chatFocus(uid);

        this.noNewMessages(uid);
        this.scrollToBottom(uid);
    };

    Chat.prototype.localCloseWindow = function (uid) {
        delete this.state[uid];
        $('[data-chat="window"][data-uid="' + uid + '"]', this.context).remove();
    };

    Chat.prototype.localSendMessage = function (partner, message) {
        var messageTime = new Date();
        var scrolledToBottom = this.isScrolledToBottom(partner.u);
        messageTime.setTime((window.parseInt(message.t)) * 1000);
        var monthNamesShort = [ 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ];
        var messageData = {
            uid:message.r ? partner.u : this.user.u,
            cmid:message.i,
            name:message.r ? partner.n : this.user.n,
            picture:message.r ? partner.p : this.user.p,
            body:message.b,
            time:messageTime.getDate() + '. ' + monthNamesShort[messageTime
                .getMonth()] + ' \'' + String(messageTime.getFullYear()).substring(2, 4) + '. @ ' + messageTime
                .getHours() + ':' + messageTime.getMinutes()
        };
        if (this.state[partner.u].last === false || this.state[partner.u].last.r !== message.r) {
            // First message in this chat window or from a different sender, append a new message container.
            var messageContainer = $(this.compileTpl('messages', messageData));
            $('[data-chat=conversation][data-uid="' + partner.u + '"]', this.context).append(messageContainer);
        }
        var messageContent = $(this.compileTpl('message', messageData));
        // Find the last message container and append the message to it.
        $('[data-chat="conversation"][data-uid="' + partner.u + '"] *[data-chat="messages"]:last', this.context).append(messageContent);
        // Store this message as the last message in this window for later use by other chat functions.
        this.state[partner.u].last = message;
        if (scrolledToBottom) {
            this.scrollToBottom(partner.u);
        }
    };

    Chat.prototype.localUpdateMessage = function (uid, messageId, messageBody) {
        $('[data-chat="message"][data-uid="' + uid + '"][data-cmid="0"]:first')
            .attr('data-cmid', messageId)
            .html(messageBody);
    };

    Chat.prototype.remoteDeactivateWindow = function (uid) {
        var sendData = {
            uid:0,
            sid:this.sid,
            token:this.nodejs.authToken
        };
        $.post(this.settings.url.activate, sendData);
    };

    Chat.prototype.remoteActivateWindow = function (uid) {
        var sendData = {
            uid:uid,
            sid:this.sid,
            token:this.nodejs.authToken
        };
        $.post(this.settings.url.activate, sendData);
    };

    Chat.prototype.remoteCloseWindow = function (uid) {
        var sendData = {
            uid:uid,
            sid:this.sid,
            token:this.nodejs.authToken
        };
        $.post(this.settings.url.close, sendData);
    };

    Chat.prototype.remoteSendMessage = function (uid, messageText) {
        var sendData = {
            uid:uid,
            message:messageText,
            sid:this.sid,
            token:this.nodejs.authToken
        };
        $.post(this.settings.url.send, sendData);
    };

    Chat.prototype.unload = function () {
        this.context.remove();
    };

    window.Chat = Chat;
})(jQuery);
