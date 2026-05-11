-- Copyright (C) 2026 SMSHUB
-- SMS templates with dynamic variable substitution.

CREATE TABLE llx_smshub_template (
	rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity          INTEGER DEFAULT 1 NOT NULL,
	code            VARCHAR(64) NOT NULL,
	label           VARCHAR(255) NOT NULL,
	content         TEXT NOT NULL,
	context         VARCHAR(32) DEFAULT 'manual' NOT NULL,
	active          TINYINT DEFAULT 1 NOT NULL,
	datec           DATETIME,
	tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat   INTEGER,
	fk_user_modif   INTEGER
) ENGINE=innodb;
