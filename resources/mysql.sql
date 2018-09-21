-- #!mysql
-- #{postbox
-- #    {init
-- #        {posts
CREATE TABLE IF NOT EXISTS postbox_post (
    post_id        BIGINT PRIMARY KEY AUTO_INCREMENT,
    sender_type    VARCHAR(100),
    sender_name    VARCHAR(100),
    recipient_type VARCHAR(100),
    recipient_name VARCHAR(100),
    priority       TINYINT,
    send_time      TIMESTAMP          DEFAULT CURRENT_TIMESTAMP,
    message        TEXT,
    is_unread      BOOL,
    INDEX (recipient_type, recipient_name, is_unread, sender_type, sender_name),
    INDEX (sender_type, sender_name),
    FULLTEXT (message)
)
    CHARSET utf8mb4
    COLLATE utf8mb4_unicode_ci;
-- #        }
-- #        {players
CREATE TABLE IF NOT EXISTS postbox_player_log (
    name        VARCHAR(100) PRIMARY KEY,
    last_online TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- #        }
-- #    }
-- #    {player
-- #        {touch
-- #            :name string
INSERT INTO postbox_player_log(name, last_online)
VALUES (:name, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE last_online = CURRENT_TIMESTAMP;
-- #        }
-- #        {group-by
-- #            {sender-type
-- #                :name string
SELECT sender_type, COUNT(*) count
FROM postbox_post
WHERE recipient_type = 'postbox.player' AND recipient_name = :name AND is_unread
GROUP BY sender_type
ORDER BY count DESC;
-- #            }
-- #            {sender-name
-- #                :name string
-- #                :senderType string
SELECT sender_type, sender_name, COUNT(*) count
FROM postbox_post
WHERE recipient_type = 'postbox.player' AND recipient_name = :name AND is_unread AND sender_type = :senderType
GROUP BY sender_name
ORDER BY count DESC;
-- #            }
-- #        }
-- #        {list
-- #            {sender-type-name
-- #                :name string
-- #                :senderType string
-- #                :senderName string
SELECT post_id, sender_type, sender_name, priority, send_time, message
FROM postbox_post
WHERE recipient_type = 'postbox.player' AND recipient_name = :name AND is_unread AND sender_type = :senderType AND
        sender_name = :senderName
ORDER BY priority DESC, send_time DESC;
-- #            }
-- #            {sender-type
-- #                :name string
-- #                :senderType string
SELECT post_id, sender_type, sender_name, priority, send_time, message
FROM postbox_post
WHERE recipient_type = 'postbox.player' AND recipient_name = :name AND is_unread AND sender_type = :senderType;
-- #            }
-- #        }
-- #        {search
SELECT post_id, sender_type, sender_name,
       recipient_type, recipient_name,
       priority, send_time, message,
       MATCH(message) AGAINST(:query IN NATURAL LANGUAGE MODE) similarity
FROM postbox_post
WHERE recipient_type = 'postbox.player' AND recipient_name = :name
ORDER BY similarity DESC;
-- #        }
-- #    }
-- #    {mark-as
-- #        {read
-- #            :ids list:int
UPDATE postbox_post
SET is_unread = FALSE
WHERE post_id IN :ids;
-- #        }
-- #        {unread
-- #            :ids list:int
UPDATE postbox_post
SET is_unread = TRUE
WHERE post_id IN :ids;
-- #        }
-- #    }
-- #}
