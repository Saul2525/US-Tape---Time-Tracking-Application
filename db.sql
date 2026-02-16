USE timeclock;

-- ROLES
CREATE TABLE ROLES (
    role_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL,
    can_edit_own TINYINT DEFAULT 1,
    can_edit_others TINYINT DEFAULT 0,
    can_approve TINYINT DEFAULT 0,
    can_manage_users TINYINT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO ROLES (role_name, can_edit_others, can_approve, can_manage_users)
VALUES 
('Employee', 0, 0, 0),
('Manager', 1, 1, 0),
('Admin', 1, 1, 1);

-- EMPLOYEES
CREATE TABLE EMPLOYEES (
    employee_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    email VARCHAR(255) UNIQUE,
    pin VARCHAR(255) NOT NULL,
    role_id BIGINT,
    is_active TINYINT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES ROLES(role_id)
);

-- WORK TIMES
CREATE TABLE WORK_TIMES (
    work_time_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    employee_id BIGINT NOT NULL,
    clock_in_time DATETIME NOT NULL,
    clock_out_time DATETIME NULL,
    clock_in_lat DECIMAL(9,6),
    clock_in_lng DECIMAL(9,6),
    clock_out_lat DECIMAL(9,6),
    clock_out_lng DECIMAL(9,6),
    clock_in_ip VARCHAR(50),
    clock_out_ip VARCHAR(50),
    approved TINYINT DEFAULT 0,
    is_edited TINYINT DEFAULT 0,
    FOREIGN KEY (employee_id) REFERENCES EMPLOYEES(employee_id),
    INDEX (employee_id, clock_in_time)
);

-- AUDIT LOG
CREATE TABLE AUDIT_LOG (
    audit_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    work_time_id BIGINT,
    employee_id BIGINT,
    changed_by BIGINT,
    old_values JSON,
    new_values JSON,
    change_reason VARCHAR(500),
    action_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);
