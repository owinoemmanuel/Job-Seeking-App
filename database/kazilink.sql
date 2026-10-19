-- KaziLink database. This is the one file you need.
-- It creates the `kazilink` database, all 16 tables and some demo data
-- (an admin, an employer, a job seeker, job categories, skills and a sample job).
--
-- To import it:
--   phpMyAdmin:   Import > choose this file > Go
--   command line: mysql -u root -p < kazilink.sql
--
-- Careful: it drops any existing `kazilink` database and creates it again,
-- so importing a second time wipes your data.

DROP DATABASE IF EXISTS kazilink;
CREATE DATABASE kazilink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kazilink;

-- 1. users: every account (job seeker, employer, admin)
CREATE TABLE users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(100) NOT NULL UNIQUE,
    phone       VARCHAR(20),
    password    VARCHAR(255) NOT NULL,
    role        ENUM('job_seeker','employer','admin') NOT NULL,
    status      ENUM('active','suspended') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. job_seekers: extra profile details for role = job_seeker
CREATE TABLE job_seekers (
    seeker_id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id              INT NOT NULL UNIQUE,
    location             VARCHAR(100),
    gender               VARCHAR(20),
    professional_summary TEXT,
    profile_photo        VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. employers: extra profile details for role = employer
CREATE TABLE employers (
    employer_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL UNIQUE,
    company_name    VARCHAR(150) NOT NULL,
    company_email   VARCHAR(100),
    company_phone   VARCHAR(20),
    location        VARCHAR(100),
    description     TEXT,
    website         VARCHAR(255),
    logo            VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. job_categories
CREATE TABLE job_categories (
    category_id    INT AUTO_INCREMENT PRIMARY KEY,
    category_name  VARCHAR(100) NOT NULL UNIQUE,
    description    TEXT
) ENGINE=InnoDB;

-- 5. jobs
-- status: pending = waiting for admin approval, active = approved and visible to seekers,
-- rejected = admin turned it down, closed = employer closed it or the deadline passed
CREATE TABLE jobs (
    job_id           INT AUTO_INCREMENT PRIMARY KEY,
    employer_id      INT NOT NULL,
    category_id      INT,
    title            VARCHAR(150) NOT NULL,
    description      TEXT NOT NULL,
    requirements     TEXT,
    location         VARCHAR(100),
    job_type         VARCHAR(50),        -- Full-time, Part-time, Internship, Contract
    experience_level VARCHAR(50),        -- Entry, Mid, Senior
    salary           DECIMAL(10,2),
    deadline         DATE,
    status           VARCHAR(30) NOT NULL DEFAULT 'pending',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employer_id) REFERENCES employers(employer_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES job_categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 6. resumes: the CV files a seeker uploaded
CREATE TABLE resumes (
    resume_id    INT AUTO_INCREMENT PRIMARY KEY,
    seeker_id    INT NOT NULL,
    file_name    VARCHAR(255) NOT NULL,
    file_path    VARCHAR(255) NOT NULL,
    is_default   BOOLEAN DEFAULT FALSE,
    uploaded_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seeker_id) REFERENCES job_seekers(seeker_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. applications
-- status goes Applied, Under Review, Shortlisted, Interview, then Accepted or Rejected
CREATE TABLE applications (
    application_id  INT AUTO_INCREMENT PRIMARY KEY,
    job_id          INT NOT NULL,
    seeker_id       INT NOT NULL,
    resume_id       INT,
    cover_letter    TEXT,
    status          VARCHAR(50) NOT NULL DEFAULT 'Applied',
    applied_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(job_id) ON DELETE CASCADE,
    FOREIGN KEY (seeker_id) REFERENCES job_seekers(seeker_id) ON DELETE CASCADE,
    FOREIGN KEY (resume_id) REFERENCES resumes(resume_id) ON DELETE SET NULL,
    UNIQUE KEY unique_application (job_id, seeker_id)
) ENGINE=InnoDB;

-- 8. skills
CREATE TABLE skills (
    skill_id    INT AUTO_INCREMENT PRIMARY KEY,
    skill_name  VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 9. job_seeker_skills: which skills each seeker picked
CREATE TABLE job_seeker_skills (
    seeker_skill_id  INT AUTO_INCREMENT PRIMARY KEY,
    seeker_id        INT NOT NULL,
    skill_id         INT NOT NULL,
    proficiency      VARCHAR(50) DEFAULT 'Intermediate',
    FOREIGN KEY (seeker_id) REFERENCES job_seekers(seeker_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE,
    UNIQUE KEY unique_seeker_skill (seeker_id, skill_id)
) ENGINE=InnoDB;

-- 10. education
CREATE TABLE education (
    education_id    INT AUTO_INCREMENT PRIMARY KEY,
    seeker_id       INT NOT NULL,
    institution     VARCHAR(150) NOT NULL,
    qualification   VARCHAR(150),
    field_of_study  VARCHAR(150),
    start_date      DATE,
    end_date        DATE,
    FOREIGN KEY (seeker_id) REFERENCES job_seekers(seeker_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 11. work_experience
CREATE TABLE work_experience (
    experience_id  INT AUTO_INCREMENT PRIMARY KEY,
    seeker_id      INT NOT NULL,
    company_name   VARCHAR(150) NOT NULL,
    job_title      VARCHAR(150),
    description    TEXT,
    start_date     DATE,
    end_date       DATE,
    FOREIGN KEY (seeker_id) REFERENCES job_seekers(seeker_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 12. saved_jobs: jobs a seeker bookmarked
CREATE TABLE saved_jobs (
    saved_id    INT AUTO_INCREMENT PRIMARY KEY,
    seeker_id   INT NOT NULL,
    job_id      INT NOT NULL,
    saved_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seeker_id) REFERENCES job_seekers(seeker_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(job_id) ON DELETE CASCADE,
    UNIQUE KEY unique_saved (seeker_id, job_id)
) ENGINE=InnoDB;

-- 13. notifications
CREATE TABLE notifications (
    notification_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT NOT NULL,
    title            VARCHAR(150) NOT NULL,
    message          TEXT,
    is_read          BOOLEAN DEFAULT FALSE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 14. admin_actions: log of what admins did
CREATE TABLE admin_actions (
    action_id    INT AUTO_INCREMENT PRIMARY KEY,
    admin_id     INT NOT NULL,
    action       VARCHAR(150) NOT NULL,
    target_type  VARCHAR(50),
    target_id    INT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 15. reports: complaints from job seekers, answered by the admin
-- (not one of the original 14 tables, the Complaints pages need it)
-- target_type says what the complaint is about. 'general' means the website
-- itself, so it has no target_id. 'reason' is what the job seeker typed and
-- the admin writes the answer in admin_reply.
-- Once a complaint is reviewed or dismissed, the job seeker and the admin can
-- each delete it. deleted_by_seeker / deleted_by_admin only hide it from that
-- person's list, and the row is removed for good when both have deleted it.
CREATE TABLE reports (
    report_id          INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id        INT NOT NULL,
    target_type        ENUM('job','user','employer','general') NOT NULL DEFAULT 'general',
    target_id          INT NULL,
    subject            VARCHAR(150) NULL,
    reason             TEXT NOT NULL,
    status             ENUM('open','reviewed','dismissed') NOT NULL DEFAULT 'open',
    admin_reply        TEXT NULL,
    replied_at         DATETIME NULL,
    deleted_by_seeker  TINYINT(1) NOT NULL DEFAULT 0,
    deleted_by_admin   TINYINT(1) NOT NULL DEFAULT 0,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 16. password_resets: one-time "forgot password" links
-- Only a SHA-256 hash of the token is saved, never the token, so a leaked
-- database can't be used to reset anyone's password. The app also creates this
-- table if it is missing, so an old database doesn't need a re-import.
CREATE TABLE password_resets (
    reset_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    token_hash  CHAR(64) NOT NULL UNIQUE,
    expires_at  DATETIME NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- demo data

-- admin login: admin@kazilink.com / Admin@123
INSERT INTO users (full_name, email, phone, password, role, status) VALUES
('System Administrator', 'admin@kazilink.com', '0700000000',
 '$2y$10$cKuyC4YgcMlCzNO2jmFnyuGVS.TaQ5xvNLrCNenLmNdobqfpk5z2S', 'admin', 'active');

-- Job categories
INSERT INTO job_categories (category_name, description) VALUES
('Information Technology', 'Software, IT support, networking and data roles'),
('Sales & Marketing', 'Sales, business development and marketing roles'),
('Finance & Accounting', 'Accounting, auditing and financial analysis roles'),
('Engineering', 'Civil, mechanical, electrical and other engineering roles'),
('Health & Medicine', 'Clinical and healthcare-related roles'),
('Education & Training', 'Teaching, training and academic roles'),
('Customer Service', 'Support, call centre and client-facing roles'),
('Administration', 'Office administration and clerical roles'),
('Human Resources', 'Recruitment, HR and people operations roles'),
('Logistics & Supply Chain', 'Procurement, warehousing and logistics roles');

-- Common skills
INSERT INTO skills (skill_name) VALUES
('Communication'),('Microsoft Excel'),('Project Management'),('Customer Service'),
('JavaScript'),('PHP'),('SQL'),('Python'),('Graphic Design'),('Accounting'),
('Sales'),('Digital Marketing'),('Data Analysis'),('Team Leadership'),('Networking');

-- Sample employer (password: Employer@123)
INSERT INTO users (full_name, email, phone, password, role, status) VALUES
('Jane Wanjiru', 'hr@abacustech.co.ke', '0711223344',
 '$2y$10$EL2pnMLcDcqBqLVjC9GqseNiqpGE3SeTJzmSQ/UpsbSLedikAjB1i', 'employer', 'active');

INSERT INTO employers (user_id, company_name, company_email, company_phone, location, description, website) VALUES
(2, 'Abacus Technologies', 'hr@abacustech.co.ke', '0711223344', 'Meru',
 'A growing ICT solutions company serving businesses across Eastern Kenya.', 'https://abacustech.co.ke');

-- Sample job seeker (password: Seeker@123)
INSERT INTO users (full_name, email, phone, password, role, status) VALUES
('Brian Kimani', 'brian.kimani@example.com', '0722334455',
 '$2y$10$K4aoQBd8almogwCbKcY2sebe9OsXRnzEXtA8MCEGagSUVNwqF0Sne', 'job_seeker', 'active');

INSERT INTO job_seekers (user_id, location, gender, professional_summary) VALUES
(3, 'Meru', 'Male', 'Diploma-level ICT professional with a passion for technical support and networking.');

-- Sample job posting
INSERT INTO jobs (employer_id, category_id, title, description, requirements, location, job_type, experience_level, salary, deadline, status) VALUES
(1, 1, 'ICT Intern', 'Provide day-to-day ICT support duties across departments, including helpdesk tickets and basic network maintenance.',
 'Diploma/Degree in ICT or a related field. Good communication skills.', 'Meru', 'Internship', 'Entry', 20000.00, '2026-10-30', 'active');

-- The password hashes above were made with PHP's password_hash() and checked
-- with password_verify(). For a fresh one: php -r "echo password_hash('Admin@123', PASSWORD_BCRYPT);"
