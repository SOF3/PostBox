-- #!mysql
-- #{postbox
-- #    {init-posts
CREATE TABLE IF NOT EXISTS postbox_posts (
	post_id     BIGINT PRIMARY KEY AUTO_INCREMENT,
	recipient   VARCHAR(100),
	message     VARCHAR(1000) CHARSET utf8mb4,
	sender_type VARCHAR(250),
	sender_name VARCHAR(250),
	priority    TINYINT,
	send_time   TIMESTAMP          DEFAULT CURRENT_TIMESTAMP,
	is_unread   BOOL               DEFAULT TRUE,
	INDEX (recipient)
);
-- #    }
-- #    {unread
-- #        {dashboard
-- #            :username string
SELECT
	sender_type,
	COUNT(DISTINCT sender_name) sender_count,
	COUNT(*)                    message_count,
	MAX(priority)               max_priority,
	SUM(priority)               sum_priority
FROM postbox_posts
WHERE is_unread AND recipient = :username
GROUP BY sender_type
ORDER BY max_priority DESC, sum_priority DESC;
-- #        }
-- #        {by-type
-- #            {expanded
-- #                :username string
-- #                :type string
SELECT
	sender_name,
	post_id,
	message,
	send_time
FROM postbox_posts
WHERE is_unread AND recipient = :username AND sender_type = :type
ORDER BY priority DESC, send_time DESC;
-- #            }
-- #            {collapse
-- #                :username string
-- #                :type string
SELECT
	sender_name,
	COUNT(*)      message_count,
	MAX(priority) max_priority,
	SUM(priority) sum_priority
FROM postbox_posts
WHERE is_unread AND recipient = :username AND sender_type = :type
GROUP BY sender_name
ORDER BY max_priority DESC, sum_priority DESC;
-- #            }
-- #        }
-- #        {by-type-name
-- #            :username string
-- #            :type string
-- #            :sender string
SELECT
	post_id,
	message,
	send_time
FROM postbox_posts
WHERE is_unread AND recipient = :username AND sender_type = :type AND sender_name = :sender
ORDER BY priority DESC, send_time DESC;
-- #        }
-- #    }
-- #    {mark-read
-- #        :username string
-- #        :ids list:int
UPDATE postbox_posts
SET is_unread = 0
WHERE recipient = :username AND post_id IN :ids;
-- #    }
-- #    {send-message
-- #        :recipient string
-- #        :senderType string
-- #        :senderName string
-- #        :priority int 0
-- #        :message string
INSERT INTO postbox_posts (recipient, sender_type, sender_name, priority, message)
VALUES (:recipient, :senderType, :senderName, :priority, :message);
-- #    }
-- #}
