-- Copyright (C) 2026 SMSHUB
-- Configurable reminder steps for unpaid invoices.

CREATE TABLE llx_smshub_relance_step (
	rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity          INTEGER DEFAULT 1 NOT NULL,
	rank_order      INTEGER NOT NULL,
	label           VARCHAR(128) NOT NULL,
	days_offset     INTEGER NOT NULL,
	template_code   VARCHAR(64) NOT NULL,
	min_amount      DECIMAL(24,8) DEFAULT 0,
	active          TINYINT DEFAULT 1 NOT NULL,
	tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
