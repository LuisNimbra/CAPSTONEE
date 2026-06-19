-- =============================================================
-- PESO CSJDM Skill Mapping & Job Referral DSS
-- Database Schema
-- =============================================================


-- ---------------------------------------------------------------
-- 1. USERS — PESO staff accounts (FR1)
-- ---------------------------------------------------------------
CREATE TABLE users (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    username     VARCHAR(50)  UNIQUE NOT NULL,
    email        VARCHAR(100) UNIQUE NOT NULL,
    password     VARCHAR(255) NOT NULL,
    full_name    VARCHAR(100) NOT NULL,
    status       ENUM('active','inactive') DEFAULT 'active',
    last_login   DATETIME,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 2. APPLICANTS — job seeker profiles (FR2)
-- ---------------------------------------------------------------
CREATE TABLE applicants (
    id                 INT PRIMARY KEY AUTO_INCREMENT,
    first_name         VARCHAR(60)  NOT NULL,
    last_name          VARCHAR(60)  NOT NULL,
    email              VARCHAR(100),
    phone              VARCHAR(20),
    age                TINYINT UNSIGNED,
    sex                ENUM('Male','Female'),
    civil_status       ENUM('Single','Married','Widowed','Separated'),
    address            TEXT,
    barangay           VARCHAR(100),
    education_level    ENUM('Elementary','High School','Vocational','College','Post-Graduate') NOT NULL,
    course             VARCHAR(150),
    school             VARCHAR(150),
    year_graduated     YEAR,
    years_experience   DECIMAL(4,1) DEFAULT 0,
    preferred_position VARCHAR(100),
    status             ENUM('active','placed','inactive') DEFAULT 'active',
    created_by         INT,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 3. APPLICANT SKILLS (normalized)
-- ---------------------------------------------------------------
CREATE TABLE applicant_skills (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    applicant_id INT NOT NULL,
    skill        VARCHAR(100) NOT NULL,
    skill_type   ENUM('Technical','Interpersonal','Other') DEFAULT 'Technical',
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 4. WORK EXPERIENCE (normalized)
-- ---------------------------------------------------------------
CREATE TABLE work_experience (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    applicant_id INT NOT NULL,
    company      VARCHAR(150),
    position     VARCHAR(100),
    years        DECIMAL(4,1),
    industry     VARCHAR(100),
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 5. JOB VACANCIES (FR3)
-- ---------------------------------------------------------------
CREATE TABLE job_vacancies (
    id                   INT PRIMARY KEY AUTO_INCREMENT,
    job_title            VARCHAR(150) NOT NULL,
    company              VARCHAR(150) NOT NULL,
    description          TEXT,
    qualifications       TEXT,
    required_education   ENUM('Elementary','High School','Vocational','College','Post-Graduate'),
    required_experience  DECIMAL(4,1) DEFAULT 0,
    salary_min           DECIMAL(10,2),
    salary_max           DECIMAL(10,2),
    slots                INT DEFAULT 1,
    status               ENUM('active','filled','inactive') DEFAULT 'active',
    created_by           INT,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 6. JOB REQUIRED SKILLS (normalized)
-- ---------------------------------------------------------------
CREATE TABLE job_required_skills (
    id     INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    skill  VARCHAR(100) NOT NULL,
    FOREIGN KEY (job_id) REFERENCES job_vacancies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 7. RECOMMENDATIONS — ML output (FR4, FR5, FR6)
-- ---------------------------------------------------------------
CREATE TABLE recommendations (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    job_id         INT NOT NULL,
    applicant_id   INT NOT NULL,
    match_score    DECIMAL(6,4),
    rank_position  INT,
    algorithm_used VARCHAR(50),
    explanation    JSON,
    status         ENUM('pending','referred','rejected') DEFAULT 'pending',
    generated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id)       REFERENCES job_vacancies(id) ON DELETE CASCADE,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 8. PLACEMENTS — feedback loop (Conceptual Framework)
-- ---------------------------------------------------------------
CREATE TABLE placements (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    applicant_id     INT NOT NULL,
    job_id           INT NOT NULL,
    employer_name    VARCHAR(150),
    position         VARCHAR(100),
    placement_date   DATE,
    transaction_type ENUM('Walk-in','Referral','Online','Job Fair') DEFAULT 'Referral',
    barangay         VARCHAR(100),
    created_by       INT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE RESTRICT,
    FOREIGN KEY (job_id)       REFERENCES job_vacancies(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by)   REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 9. ACTIVITY LOGS
-- ---------------------------------------------------------------
CREATE TABLE activity_logs (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    user_id    INT,
    action     VARCHAR(150) NOT NULL,
    module     VARCHAR(50),
    details    TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 10. DATASET UPLOADS — Excel import tracking
-- ---------------------------------------------------------------
CREATE TABLE dataset_uploads (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    filename      VARCHAR(255),
    original_name VARCHAR(255),
    record_count  INT DEFAULT 0,
    uploaded_by   INT,
    status        ENUM('pending','processed','failed') DEFAULT 'pending',
    uploaded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 11. ML MODEL METADATA
-- ---------------------------------------------------------------
CREATE TABLE ml_models (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    model_name     VARCHAR(50) NOT NULL,
    accuracy       DECIMAL(6,4),
    precision_score DECIMAL(6,4),
    recall_score   DECIMAL(6,4),
    f1_score       DECIMAL(6,4),
    is_active      TINYINT(1) DEFAULT 0,
    trained_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- SEED DATA
-- ---------------------------------------------------------------
INSERT INTO users (username, email, password, full_name)
VALUES ('admin', 'admin@peso-csjdm.gov.ph',
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'System Administrator');
-- default password: password

INSERT INTO applicants
    (first_name, last_name, email, phone, age, sex, civil_status, barangay,
     education_level, course, school, year_graduated, years_experience, preferred_position, status, created_by)
VALUES
('Maria','Santos','maria.santos@email.com','09171234567',24,'Female','Single','Mulawin',
 'College','BS Information Technology','Bulacan State University',2023,1,'Software Developer','active',1),
('Juan','Dela Cruz','juan.delacruz@email.com','09181234567',28,'Male','Single','Sapang Palay',
 'College','BS Business Administration','Philippine School of Business',2018,4,'Administrative Assistant','active',1),
('Ana','Reyes','ana.reyes@email.com','09191234567',22,'Female','Single','San Isidro',
 'Vocational','Computer Hardware Servicing','TESDA',2022,0,'Computer Technician','active',1),
('Pedro','Lim','pedro.lim@email.com','09201234567',35,'Male','Married','Minuyan',
 'College','BS Accountancy','Far Eastern University',2012,10,'Accountant','active',1),
('Rosa','Aquino','rosa.aquino@email.com','09211234567',26,'Female','Single','Dulong Bayan',
 'College','BS Marketing','De La Salle University',2021,2,'Sales Associate','active',1),
('Carlos','Fernandez','carlos.fernandez@email.com','09221234567',30,'Male','Married','Citrus',
 'College','BS Computer Science','University of Santo Tomas',2016,7,'Systems Analyst','active',1),
('Luz','Villanueva','luz.villanueva@email.com','09231234567',23,'Female','Single','Tungkong Mangga',
 'College','BS Nursing','Philippine Nursing University',2023,0,'Customer Service Representative','active',1),
('Miguel','Cruz','miguel.cruz@email.com','09241234567',27,'Male','Single','San Martin',
 'High School','General',NULL,2015,5,'Warehouse Staff','active',1);

INSERT INTO applicant_skills (applicant_id, skill, skill_type) VALUES
(1,'PHP Programming','Technical'),(1,'JavaScript','Technical'),(1,'MySQL','Technical'),
(1,'Team Collaboration','Interpersonal'),(1,'Problem Solving','Interpersonal'),
(2,'Microsoft Office','Technical'),(2,'Data Entry','Technical'),
(2,'Communication','Interpersonal'),(2,'Time Management','Interpersonal'),
(3,'Computer Repair','Technical'),(3,'Network Troubleshooting','Technical'),
(3,'Customer Service','Interpersonal'),
(4,'Accounting Software','Technical'),(4,'Financial Reporting','Technical'),
(4,'Analytical Thinking','Interpersonal'),(4,'Attention to Detail','Interpersonal'),
(5,'Social Media Marketing','Technical'),(5,'Sales','Technical'),
(5,'Persuasion','Interpersonal'),(5,'Negotiation','Interpersonal'),
(6,'Systems Analysis','Technical'),(6,'SQL','Technical'),(6,'Java','Technical'),
(6,'Leadership','Interpersonal'),
(7,'Customer Service','Interpersonal'),(7,'Communication','Interpersonal'),
(7,'Computer Literacy','Technical'),
(8,'Forklift Operation','Technical'),(8,'Inventory Management','Technical'),
(8,'Physical Stamina','Interpersonal');

INSERT INTO job_vacancies
    (job_title, company, description, qualifications, required_education, required_experience,
     salary_min, salary_max, slots, status, created_by)
VALUES
('Customer Service Representative','Concentrix Philippines',
 'Handle customer inquiries via phone and chat. Provide product support and resolve issues.',
 'Good communication skills; basic computer literacy; flexible schedule.',
 'High School',0,18000,22000,5,'active',1),
('Administrative Assistant','San Jose del Monte City Hall',
 'Provide administrative support to the department. Maintain records and coordinate meetings.',
 'College graduate preferred; proficient in MS Office; detail-oriented.',
 'College',1,16000,20000,2,'active',1),
('Software Developer','Accenture Philippines',
 'Develop and maintain web applications. Collaborate with cross-functional teams.',
 'BS Computer Science or IT graduate; proficiency in PHP or JavaScript.',
 'College',2,30000,50000,3,'active',1),
('Sales Associate','SM Supermalls',
 'Assist customers, process transactions, and maintain product displays.',
 'High school graduate; good communication; willing to work on weekends.',
 'High School',0,14000,18000,10,'active',1),
('Accountant','BDO Unibank',
 'Prepare financial statements, manage accounts, and ensure regulatory compliance.',
 'CPA or BS Accountancy graduate; at least 2 years experience.',
 'College',2,25000,35000,2,'active',1);

INSERT INTO job_required_skills (job_id, skill) VALUES
(1,'Customer Service'),(1,'Communication'),(1,'Computer Literacy'),
(2,'Microsoft Office'),(2,'Data Entry'),(2,'Communication'),(2,'Time Management'),
(3,'PHP Programming'),(3,'JavaScript'),(3,'MySQL'),(3,'Problem Solving'),
(4,'Sales'),(4,'Communication'),(4,'Customer Service'),
(5,'Accounting Software'),(5,'Financial Reporting'),(5,'Analytical Thinking'),(5,'Attention to Detail');

INSERT INTO placements (applicant_id, job_id, employer_name, position, placement_date, transaction_type, barangay, created_by)
VALUES
(7,1,'Concentrix Philippines','Customer Service Representative','2024-03-15','Referral','Tungkong Mangga',1),
(2,2,'San Jose del Monte City Hall','Administrative Assistant','2024-04-01','Referral','Sapang Palay',1),
(5,4,'SM Supermalls','Sales Associate','2024-04-10','Walk-in','Dulong Bayan',1);

UPDATE applicants SET status='placed' WHERE id IN (7,2,5);
UPDATE job_vacancies SET slots = slots - 1 WHERE id IN (1,2,4);

-- ---------------------------------------------------------------
-- MIGRATION v2: Client Feedback Implementation
-- Safe to run on existing databases (IF NOT EXISTS / IF NOT EXISTS)
-- ---------------------------------------------------------------

ALTER TABLE applicants
  ADD COLUMN IF NOT EXISTS sector ENUM(
    'None','PWD','4Ps Beneficiary','Senior Citizen',
    'OFW Returnee','Displaced Worker','Indigenous People','Solo Parent'
  ) DEFAULT 'None' AFTER civil_status;

ALTER TABLE placements
  ADD COLUMN IF NOT EXISTS employer_confirmation ENUM(
    'Pending','Hired','Declined','No-Show','Under Evaluation'
  ) DEFAULT 'Pending' AFTER transaction_type,
  ADD COLUMN IF NOT EXISTS employer_report_date DATE NULL AFTER employer_confirmation,
  ADD COLUMN IF NOT EXISTS employer_remarks TEXT NULL AFTER employer_report_date;

CREATE TABLE IF NOT EXISTS referrals (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    applicant_id  INT NOT NULL,
    job_id        INT NOT NULL,
    referral_date DATE NOT NULL,
    outcome       ENUM('Pending','Hired','Declined','No-Show','Withdrew') DEFAULT 'Pending',
    outcome_date  DATE NULL,
    placement_id  INT NULL,
    notes         TEXT,
    created_by    INT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE RESTRICT,
    FOREIGN KEY (job_id)       REFERENCES job_vacancies(id) ON DELETE RESTRICT,
    FOREIGN KEY (placement_id) REFERENCES placements(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)   REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS applicant_certifications (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    applicant_id  INT NOT NULL,
    cert_name     VARCHAR(150) NOT NULL,
    issuing_body  VARCHAR(150),
    date_issued   DATE NULL,
    expiry_date   DATE NULL,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sample sector data for seed applicants
UPDATE applicants SET sector='PWD'             WHERE id=3;
UPDATE applicants SET sector='4Ps Beneficiary' WHERE id=8;
UPDATE applicants SET sector='Senior Citizen'  WHERE id=4;

-- Update seed placements with employer confirmation
UPDATE placements SET employer_confirmation='Hired', employer_report_date='2024-03-20'
  WHERE applicant_id=7 AND job_id=1;
UPDATE placements SET employer_confirmation='Hired', employer_report_date='2024-04-05'
  WHERE applicant_id=2 AND job_id=2;
UPDATE placements SET employer_confirmation='Pending'
  WHERE applicant_id=5 AND job_id=4;
