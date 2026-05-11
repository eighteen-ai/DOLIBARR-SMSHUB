ALTER TABLE llx_smshub_log ADD INDEX idx_smshub_log_datec (datec);
ALTER TABLE llx_smshub_log ADD INDEX idx_smshub_log_source (source, fk_source);
ALTER TABLE llx_smshub_log ADD INDEX idx_smshub_log_status (status);
ALTER TABLE llx_smshub_log ADD INDEX idx_smshub_log_phone (phone);
