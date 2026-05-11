-- Copyright (C) 2026 SMSHUB
-- Tracks which reminder step has been sent for each invoice.

CREATE TABLE llx_smshub_relance_status (
	rowid                   INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity                  INTEGER DEFAULT 1 NOT NULL,
	fk_facture              INTEGER NOT NULL,
	last_step_rank          INTEGER DEFAULT 0 NOT NULL,
	last_sent_at            DATETIME,
	stopped                 TINYINT DEFAULT 0 NOT NULL,
	stop_reason             VARCHAR(255),
	tms                     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
