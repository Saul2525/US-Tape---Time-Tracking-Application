-- ROLES
CREATE TABLE ROLES (
    role_id          BIGINT       IDENTITY (1, 1) PRIMARY KEY,
    role_name        VARCHAR (50) NOT NULL,
    can_edit_own     BIT          DEFAULT 1 NOT NULL,
    can_edit_others  BIT          DEFAULT 0 NOT NULL,
    can_approve      BIT          DEFAULT 0 NOT NULL,
    can_manage_users BIT          DEFAULT 0 NOT NULL,
    created_at       DATETIME2    DEFAULT SYSUTCDATETIME() NOT NULL
);

INSERT  INTO ROLES (
    role_name,
    can_edit_others,
    can_approve,
    can_manage_users
)
VALUES             ('Employee', 0, 0, 0),
                   ('Manager', 1, 1, 0),
                   ('Admin', 1, 1, 1);

-- EMPLOYEES
CREATE TABLE EMPLOYEES (
    employee_id BIGINT        IDENTITY (1, 1) PRIMARY KEY,
    first_name  VARCHAR (100) NOT NULL,
    last_name   VARCHAR (100) NOT NULL,
    email       VARCHAR (255) NOT NULL UNIQUE,
    pin         CHAR (5)      NOT NULL UNIQUE,
    password    VARCHAR (255) NULL,
    role_id     BIGINT        NOT NULL,
    is_active   BIT           DEFAULT 1 NOT NULL,
    created_at  DATETIME2     DEFAULT SYSUTCDATETIME() NOT NULL,
    CONSTRAINT FK_EMPLOYEES_ROLES FOREIGN KEY (role_id) REFERENCES ROLES (role_id),
    CONSTRAINT CK_EMPLOYEES_PIN CHECK (pin NOT LIKE '%[^0-9]%')
);

-- WORK TIMES
CREATE TABLE WORK_TIMES (
    work_time_id   BIGINT         IDENTITY (1, 1) PRIMARY KEY,
    employee_id    BIGINT         NOT NULL,
    clock_in_time  DATETIME2      NOT NULL,
    clock_out_time DATETIME2      NULL,
    clock_in_lat   DECIMAL (9, 6) NULL,
    clock_in_lng   DECIMAL (9, 6) NULL,
    clock_out_lat  DECIMAL (9, 6) NULL,
    clock_out_lng  DECIMAL (9, 6) NULL,
    clock_in_ip    VARCHAR (50)   NULL,
    clock_out_ip   VARCHAR (50)   NULL,
    approved       BIT            DEFAULT 0 NOT NULL,
    is_edited      BIT            DEFAULT 0 NOT NULL,
    manager_note   VARCHAR (255)  NULL,
    CONSTRAINT FK_WORK_TIMES_EMPLOYEES FOREIGN KEY (employee_id) REFERENCES EMPLOYEES (employee_id)
);

-- AUDIT LOG
CREATE TABLE AUDIT_LOG (
    audit_id         BIGINT         IDENTITY (1, 1) PRIMARY KEY,
    work_time_id     BIGINT         NULL,
    employee_id      BIGINT         NULL,
    changed_by       BIGINT         NULL,
    old_values       NVARCHAR (MAX) NULL,
    new_values       NVARCHAR (MAX) NULL,
    change_reason    VARCHAR (500)  NULL,
    action_timestamp DATETIME2      DEFAULT SYSUTCDATETIME() NOT NULL
);

-- INDEXES
CREATE INDEX IX_WORK_TIMES_EMPLOYEE_CLOCK_IN
    ON WORK_TIMES(employee_id, clock_in_time);