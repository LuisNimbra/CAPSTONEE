-- PESO DSS v2 Migration — safe to run on MariaDB 10.4+
-- Adds sector, employer confirmation, referrals, and certifications

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

-- Sample sector data for seed applicants (skips if already set)
UPDATE applicants SET sector='PWD'             WHERE id=3 AND sector='None';
UPDATE applicants SET sector='4Ps Beneficiary' WHERE id=8 AND sector='None';
UPDATE applicants SET sector='Senior Citizen'  WHERE id=4 AND sector='None';

-- Update seed placements with employer confirmation
UPDATE placements SET employer_confirmation='Hired', employer_report_date='2024-03-20'
  WHERE applicant_id=7 AND job_id=1 AND employer_confirmation='Pending';
UPDATE placements SET employer_confirmation='Hired', employer_report_date='2024-04-05'
  WHERE applicant_id=2 AND job_id=2 AND employer_confirmation='Pending';

SELECT 'Migration complete.' AS status;
