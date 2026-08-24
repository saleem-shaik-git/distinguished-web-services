ALTER TABLE projects
  ADD COLUMN client_name VARCHAR(180) NULL AFTER category,
  ADD COLUMN industry VARCHAR(150) NULL AFTER client_name,
  ADD COLUMN project_type VARCHAR(150) NULL AFTER industry,
  ADD COLUMN project_duration VARCHAR(100) NULL AFTER project_type,
  ADD COLUMN project_scope TEXT NULL AFTER project_duration,
  ADD COLUMN objectives TEXT NULL AFTER project_scope,
  ADD COLUMN strategy TEXT NULL AFTER objectives,
  ADD COLUMN key_features TEXT NULL AFTER strategy,
  ADD COLUMN results TEXT NULL AFTER key_features,
  ADD COLUMN gallery JSON NULL AFTER image,
  ADD COLUMN meta_title VARCHAR(255) NULL AFTER results,
  ADD COLUMN meta_description VARCHAR(500) NULL AFTER meta_title;
