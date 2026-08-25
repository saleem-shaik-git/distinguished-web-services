CREATE TABLE IF NOT EXISTS project_costs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 project_id BIGINT UNSIGNED NOT NULL,
 category VARCHAR(100) NOT NULL,
 description VARCHAR(255) NULL,
 amount DECIMAL(15,2) NOT NULL DEFAULT 0,
 currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
 incurred_on DATE NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_project_costs_project(project_id), INDEX idx_project_costs_date(incurred_on),
 CONSTRAINT fk_project_costs_project FOREIGN KEY(project_id) REFERENCES client_projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS project_time_entries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 project_id BIGINT UNSIGNED NOT NULL,
 staff_id BIGINT UNSIGNED NOT NULL,
 task_id BIGINT UNSIGNED NULL,
 hours DECIMAL(8,2) NOT NULL DEFAULT 0,
 billable TINYINT(1) NOT NULL DEFAULT 1,
 description VARCHAR(255) NULL,
 work_date DATE NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_time_project(project_id), INDEX idx_time_staff(staff_id), INDEX idx_time_task(task_id), INDEX idx_time_date(work_date),
 CONSTRAINT fk_time_project FOREIGN KEY(project_id) REFERENCES client_projects(id) ON DELETE CASCADE,
 CONSTRAINT fk_time_staff FOREIGN KEY(staff_id) REFERENCES staff_members(id) ON DELETE RESTRICT,
 CONSTRAINT fk_time_task FOREIGN KEY(task_id) REFERENCES project_tasks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;