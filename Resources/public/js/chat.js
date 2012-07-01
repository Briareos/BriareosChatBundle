(function ($) {
    "use strict";

    var Chat = {};
    window.Chat = Chat;

    window.Nodejs.callbacks.chat = {
        callback:function (message) {
            if (!message.data) {
                return;
            }
            console.log(message.data);
            // Each request made by the chat sends a randomly generated tab ID
            // (tid), unique to every tab, which is broadcast back in the socket
            // message. If those tid's match, it means the request originated from
            // this tab. This is done so no duplicate operations occur.
            var origin = (message.data.tid === Chat.tid);
            // These commands reflect user's actions, such as open (window), close,
            // activate, etc.
            switch (message.data.command) {
                case 'pong':
                    Chat.pong = new Date().getTime() / 1000;
                    break;

                case 'message':
                    if (message.data.receiver.u == Chat.user.u) {
                        // Received message.
                        message.data.message.r = true;
                        // Is this a new window?
                        if ($.inArray(message.data.sender.u, Chat.data.v) == -1) {
                            // Is there an active window already or is this window already
                            // open?
                            if (!Chat.data.a && $
                                .inArray(message.data.sender.u, Chat.data.v) == -1) {
                                // Not among open windows and there are no open
                                // windows, open
                                // and activate this one, but don't focus it
                                Chat.openWindow(message.data.sender);
                                Chat.command.activateWindow(message.data.sender.u);
                            } else {
                                // New window, open it
                                Chat.openWindow(message.data.sender);
                            }
                        }
                        // If this chat window is scrolled to the bottom we should
                        // scroll it
                        // again after we append the message
                        var scroll = Chat.isScrolledToBottom(message.data.sender.u);
                        Chat.appendMessage(message.data.sender, message.data.message);
                        if (scroll) {
                            Chat.scrollToBottom(message.data.sender.u);
                        }
                        // The window that we appended messages to wasn't active,
                        // meaning
                        // that we should notify the user of new message(s)
                        if (Chat.data.a != message.data.sender.u) {
                            Chat.newMessages(message.data.sender.u);
                        }
                        // Finally, update our local cache
                        Chat.data.w[message.data.sender.u].m[message.data.message.i] = message.data.message;
                    } else {
                        // Sent message
                        message.data.message.r = false;
                        // Is this the tab that the user sent the message from?
                        if (!origin) {
                            // Message is sent from another tab, just append it in
                            // the chat
                            var scroll = Chat
                                .isScrolledToBottom(message.data.receiver.u);
                            Chat
                                .appendMessage(message.data.receiver, message.data.message);
                            if (scroll) {
                                Chat.scrollToBottom(message.data.receiver.u);
                            }
                        } else {
                            // Message is sent from this tab, and is already in the
                            // window,
                            // but without an ID, set it now
                            // Modules can also alter messages, such as converting
                            // YouTube
                            // links to videos, we should alter the message at this
                            // point
                            $('#chat *[data-chat=message][data-uid=' + message.data.receiver.u + '][data-cmid=0]:first')
                                .attr('data-cmid', message.data.message.i)
                                .html(message.data.message.b);
                        }
                        Chat.data.w[message.data.receiver.u].m[message.data.message.i] = message.data.message;
                    }
                    break;

                case 'close':
                    if (!origin) {
                        $('#chat *[data-chat=toggle][data-uid=' + message.data.uid + ']')
                            .parent().remove();
                        var index = $.inArray(message.data.uid, Chat.data.v);
                        if (index != -1) {
                            Chat.data.v.splice(index, index + 1);
                        }
                        if (Chat.data.a == message.data.uid) {
                            Chat.data.a = 0;
                        }
                        delete Chat.data.w[message.data.uid];
                    }
                    break;

                case 'activate':
                    if (!origin) {
                        $('#chat-windows .window-toggle').parent().removeClass('active')
                            .children('.panel').hide();
                        if (message.data.uid) {
                            var index = $.inArray(message.data.uid, Chat.data.v);
                            if (index == -1) {
                                Chat.openWindow(message.data.d);
                            }
                            $('#chat-windows .window-toggle[data-uid=' + message.data.uid + ']')
                                .parent().addClass('active').children('.panel').show()
                                .find('.message-text').focus();
                            Chat.scrollToBottom(message.data.uid);
                            Chat.noNewMessages(message.data.uid);
                        }
                        Chat.data.a = message.data.uid;
                    }
                    break;
            }
        }
    };

    Chat.newMessages = function (uid) {
        if (Chat.data.a != uid) {
            $('#chat-windows .window-toggle[data-uid=' + uid + ']').parent()
                .addClass('new-messages');
        }
    };

    Chat.noNewMessages = function (uid) {
        $('#chat-windows .window-toggle[data-uid=' + uid + ']').parent()
            .removeClass('new-messages');
    };

    Chat.escape = function (text) {
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    };

    Chat.generateTid = function () {
        var chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz";
        var tidLength = 12;
        var tid = '';
        for (var i = 0; i < tidLength; i++) {
            var rnum = Math.floor(Math.random() * chars.length);
            tid += chars.substring(rnum, rnum + 1);
        }
        return tid;
    };

    Chat.compileTpl = function (html, variables) {
        for (var i in variables) {
            html = html.replace(new RegExp('\\$\{' + i + '\}', 'gm'), variables[i]);
        }
        return html;
    };

    Chat.ping = function () {
        var now = parseInt(new Date().getTime() / 1000);
        if ((now - Chat.pong) >= 300) {
            $.post(Chat.settings.pingUrl);
            // console.log("Pinging now "+new Date().toUTCString());
        } else {
            // console.log("Ping skipped "+new Date().toUTCString());
        }
    };

    Chat.load = function (data) {
        // Store global data object that should be preserved across pages
        Chat.tid = Chat.generateTid();
        Chat.data = data;
        Chat.data.a = parseInt(Chat.data.a, 10);
        Chat.local = {};
        Chat.pong = 0;
        setInterval(Chat.ping, 303000);
        Chat.user = {
            u:data.u,
            n:data.n,
            p:data.p
        };
        // This variable stores our javascript templates
        var tpl = Chat.settings.tpl;
        // First generate the status block
        var onlineUsersHtml = '';

        // Iterate through all online users
        var countUsers = 0;
        for (var i in data.o) {
            var onlineUser = data.o[i];
            // n:name, s:status, p:picture, u:uid
            var userData = {
                uid:onlineUser.u,
                name:onlineUser.n,
                status:onlineUser.s,
                picture:onlineUser.p
            };
            onlineUsersHtml += Chat.compileTpl(tpl.user, userData);
            countUsers++;
        }
        var onlineUsersData = {
            users:onlineUsersHtml,
            count:countUsers
        };
        onlineUsersHtml = Chat.compileTpl(tpl.status, onlineUsersData);
        $('#chat-status').html(onlineUsersHtml);

        // data.v is an array that stores all open window's uid's in user's
        // session
        // Iterate through all open chat windows
        for (var i in data.v) {
            var uid = data.v[i];
            // This is the chat window we're currently working with
            var chatWindow = data.w[uid];
            // This variable should store the last message displayed in this
            // chat
            // window
            Chat.local[uid] = {};
            Chat.local[uid].last = false;
            // User's chat partner in this window
            var partner = chatWindow.d;
            // Insert our chat window in DOM at this point
            var chatWindowHtml = Chat.generateWindow(partner);
            $('#chat-windows').prepend(chatWindowHtml);
            for (var j in chatWindow.m) {
                // Iterate through all messages in this window
                var message = chatWindow.m[j];
                Chat.appendMessage(partner, message);
            }
            if (chatWindow.e) {
                Chat.newMessages(uid);
            }
        }

        if (data.a) {
            $('#chat-windows .window-toggle[data-uid=' + data.a + ']').parent()
                .addClass('active').children('.panel').show().find('.message-text')
                .focus();
            // Scroll this chat window to bottom
            Chat.scrollToBottom(data.a);
        }
    };

    Chat.isScrolledToBottom = function (uid) {
        if (Chat.data.a != uid) {
            // This chat window is not open at all.
            return false;
        }
        var convo = $('#chat-windows *[data-chat=focus][data-uid=' + uid + ']');
        var scrolled = (convo.prop('scrollHeight') - convo.scrollTop()) == convo.height();
        return scrolled;
    };

    Chat.scrollToBottom = function (uid) {
        // Scroll this chat window to bottom
        var convo = $('#chat-windows .body[data-uid=' + uid + ']');
        convo.scrollTop(convo.prop('scrollHeight') - convo.height());
    };

    Chat.openWindow = function (partner) {
        var chatWindow = Chat.data.w[partner.u];
        console.log(chatWindow);
        if (!chatWindow) {
            Chat.local[partner.u] = {};
            Chat.local[partner.u].last = false;
            Chat.data.v.push(parseInt(partner.u));
            Chat.data.w[partner.u] = {
                d:partner,
                m:{}
            };
            var chatWindowHtml = Chat.generateWindow(partner);
            $('#chat-windows').prepend(chatWindowHtml);
        }
    };

    Chat.appendMessage = function (partner, message) {
        var messageTime = new Date();
        messageTime.setTime((parseInt(message.t)) * 1000);
        var monthNamesShort = [ 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ];
        var messageData = {
            uid:message.r ? partner.u : Chat.user.u,
            cmid:message.i,
            name:message.r ? partner.n : Chat.user.n,
            picture:message.r ? partner.p : Chat.user.p,
            body:message.b,
            time:messageTime.getDate() + '. ' + monthNamesShort[messageTime
                .getMonth()] + ' \'' + String(messageTime.getFullYear()).substring(2, 4) + '. @ ' + messageTime
                .getHours() + ':' + messageTime.getMinutes()
        };
        if (!Chat.local[partner.u].last || Chat.local[partner.u].last.r != message.r) {
            // First message in this chat window or from a different sender,
            // load
            // new message container
            var messageContainer = Chat
                .compileTpl(Chat.settings.tpl.messages, messageData);
            $('#chat *[data-chat=conversation][data-uid=' + partner.u + ']')
                .append(messageContainer);
        }
        var messageHtml = Chat
            .compileTpl(Chat.settings.tpl.message, messageData);
        $('#chat *[data-chat=conversation][data-uid=' + partner.u + '] *[data-chat=messages]:last')
            .append(messageHtml);
        // Store this message as the last message in this window for later use
        // by
        // other chat functions
        Chat.local[partner.u].last = message;
    };

    Chat.generateWindow = function (user, messages) {
        messages = messages ? messages : '';
        var chatWindowData = {
            uid:user.u,
            label:user.n,
            picture:user.p,
            name:user.n,
            messages:messages
        };
        return Chat
            .compileTpl(Chat.settings.tpl.window, chatWindowData);
    };

    Chat.command = {};

    Chat.command.sendMessage = function (uid, messageText) {
        var sendData = {
            uid:uid,
            message:messageText,
            tid:Chat.tid
        };
        var message = {
            i:0,
            t:new Date().getTime() / 1000,
            b:Chat.escape(messageText),
            r:false
        };
        var scroll = Chat.isScrolledToBottom(uid);
        Chat.appendMessage(Chat.data.w[uid].d, message);
        if (scroll) {
            Chat.scrollToBottom(uid);
        }
        $.post(Chat.settings.sendUrl, sendData);
    };

    Chat.command.activateWindow = function (uid) {
        if (Chat.data.w[uid]) {
            if (Chat.data.a != uid) {
                $('#chat-windows .window-toggle[data-uid!=' + uid + ']').parent()
                    .removeClass('active').children('.panel').hide();
                $('#chat-windows .window-toggle[data-uid=' + uid + ']').parent()
                    .addClass('active').children('.panel').show();
                Chat.noNewMessages(uid);
                Chat.data.a = uid;
                Chat.scrollToBottom(uid);
                var sendData = {
                    uid:uid,
                    tid:Chat.tid
                };
                $.post(Chat.settings.activateUrl, sendData);
            }
            $('#chat textarea[data-uid=' + uid + ']').focus();
        }
    };

    Chat.command.deactivateWindow = function () {
        if (Chat.data.a) {
            $('#chat-windows .window-toggle').parent().removeClass('active')
                .children('.panel').hide();
            Chat.data.a = 0;
            var sendData = {
                uid:0,
                tid:Chat.tid
            };
            $.post(Chat.settings.activateUrl, sendData);
        }
    };

    Chat.command.closeWindow = function (uid) {
        if (Chat.data.a == uid) {
            Chat.data.a = 0;
        }
        var sendData = {
            uid:uid,
            tid:Chat.tid
        };
        $.post(Chat.settings.closeUrl, sendData);
        delete Chat.local[uid];
        var index = $.inArray(uid, Chat.data.v);
        if (index != -1) {
            Chat.data.v.splice(index, index + 1);
        }
        delete Chat.data.w[uid];
        $('#chat-windows .window-toggle[data-uid=' + uid + ']').parent().remove();
    };

    $('#chat .window-toggle').live('click', function () {
        if ($(this).parent().hasClass('active')) {
            if (!$(this).parent('#chat-status').length) {
                // Chat window has closed
                Chat.command.deactivateWindow();
            } else {
                $(this).parent().removeClass('active').children('.panel').hide();
            }
        } else {
            if (!$(this).parent('#chat-status').length) {
                // Chat window has opened
                var uid = $(this).data('uid');
                Chat.command.activateWindow(uid);
            } else {
                $(this).parent().addClass('active').children('.panel').show();
            }
        }
        return false;
    });

    $('.chat-user').live('click', function () {
        var uid = $(this).data('uid');
        if ($.inArray(uid, Chat.data.v) == -1) {
            var partner = {
                u:uid,
                n:$(this).data('name'),
                p:$(this).data('picture')

            };
            Chat.openWindow(partner);
        }
        Chat.command.activateWindow(uid);
        return false;
    });

    $('#chat *[data-chat=minimize]').live('click', function (e) {
        if ($(e.srcElement).data('chat') == 'minimize') {
            Chat.command.deactivateWindow();
        }
    });

    $('#chat *[data-chat=close]').live('click', function () {
        var uid = $(this).data('uid');
        Chat.command.closeWindow(uid);
    });

    $('#chat *[data-chat=focus]').live('click', function () {
        Chat.command.activateWindow($(this).data('uid'));
    });

    $('#chat-windows .message-text').live('keyup', function () {
        var cloneId = 'clone-' + $(this).data('uid');
        var $clone = $('#' + cloneId);
        if (!$clone.length) {
            $clone = $('<div />').attr('id', cloneId).addClass('message-text').css({
                maxHeight:'none',
                position:'absolute',
                wordWrap:'break-word',
                height:'auto',
                display:'none'
            });
            $(this).parent().prepend($clone);
        }
        $clone.html($(this).val().replace(/&/g, '&amp;')
            .replace(/ {2}/g, ' &nbsp;').replace(/<|>/g, '&gt;')
            .replace(/\n/g, '<br />') + "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;");
        $(this).css('height', $clone.height());
    });

    $('#chat .message-input textarea').live('keydown', function (e) {
        if (e.keyCode == 13) {
            Chat.command.sendMessage($(this).data('uid'), $(this).val());
            $(this).val('');
            return false;
        }
    });

    Chat.initialize = function (settings) {
        Chat.settings = settings;
        $('body').append(Chat.compileTpl(Chat.settings.tpl.chat));
        $.getJSON(Chat.settings.cacheUrl, null, Chat.load);
    };
})(jQuery);
