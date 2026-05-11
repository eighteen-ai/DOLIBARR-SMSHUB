-- Copyright (C) 2026 SMSHUB
-- Journal of every SMS sent (or attempted).

CREATE TABLE llx_smshub_log (
	rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity          INTEGER DEFAULT 1 NOT NULL,
	datec           DATETIME NOT NULL,
	fk_user         INTEGER,
	phone           VARCHAR(32) NOT NULL,
	message         TEXT NOT NULL,
	source          VARCHAR(32),
	fk_source       INTEGER,
	template_code   VARCHAR(64),
	status          VARCHAR(16) DEFAULT 'pending' NOT NULL,
	task_id         INTEGER,
	scheduled_at    DATETIME,
	error_message   TEXT,
	tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
