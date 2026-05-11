ALTER TABLE llx_smshub_template ADD UNIQUE INDEX uk_smshub_template_code (code, entity);
ALTER TABLE llx_smshub_template ADD INDEX idx_smshub_template_context (context);
