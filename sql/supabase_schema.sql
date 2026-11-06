-- Supabase PostgreSQL Schema (Base Schema - No Data)
-- Matches the live public tables, including per-course targets.
-- For fresh setup; CREATE TABLE IF NOT EXISTS does not migrate existing tables.

CREATE TABLE IF NOT EXISTS roles (
    role_id INT PRIMARY KEY,
    role_name VARCHAR(64) NOT NULL
);

-- Default role mappings
INSERT INTO roles (role_id, role_name) VALUES 
    (1, 'Student'),
    (2, 'Professor'),
    (3, 'Student')
ON CONFLICT (role_id) DO NOTHING;

CREATE TABLE IF NOT EXISTS users (
    user_id SERIAL PRIMARY KEY,
    full_name VARCHAR(100),
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL DEFAULT 3 REFERENCES roles(role_id) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

CREATE TABLE IF NOT EXISTS otp_verifications (
    id SERIAL PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL DEFAULT 3,
    otp_code VARCHAR(10) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_otp_verifications_email ON otp_verifications(email);

CREATE TABLE IF NOT EXISTS grade_templates (
    template_id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(user_id) ON DELETE CASCADE,
    course_code VARCHAR(20),
    course_title VARCHAR(100),
    class_code VARCHAR(30) UNIQUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    course_unit NUMERIC(3,1) NOT NULL DEFAULT 3.0 CHECK (course_unit > 0)
);

CREATE INDEX IF NOT EXISTS idx_grade_templates_user ON grade_templates(user_id);
CREATE INDEX IF NOT EXISTS idx_grade_templates_class_code ON grade_templates(class_code);

CREATE TABLE IF NOT EXISTS midterms_template_components (
    id SERIAL PRIMARY KEY,
    template_id INT NOT NULL REFERENCES grade_templates(template_id) ON DELETE CASCADE,
    component_name VARCHAR(100) NOT NULL,
    weight INT NOT NULL DEFAULT 0 CHECK (weight BETWEEN 0 AND 100),
    is_exam BOOLEAN NOT NULL DEFAULT FALSE
);
CREATE INDEX IF NOT EXISTS idx_midterms_components_template ON midterms_template_components(template_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_midterms_one_exam ON midterms_template_components(template_id) WHERE is_exam;

CREATE TABLE IF NOT EXISTS finals_template_components (
    id SERIAL PRIMARY KEY,
    template_id INT NOT NULL REFERENCES grade_templates(template_id) ON DELETE CASCADE,
    component_name VARCHAR(100) NOT NULL,
    weight INT NOT NULL DEFAULT 0 CHECK (weight BETWEEN 0 AND 100),
    is_exam BOOLEAN NOT NULL DEFAULT FALSE
);
CREATE INDEX IF NOT EXISTS idx_finals_components_template ON finals_template_components(template_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_finals_one_exam ON finals_template_components(template_id) WHERE is_exam;

CREATE TABLE IF NOT EXISTS enrolled_courses (
    enrollment_id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    template_id INT NOT NULL REFERENCES grade_templates(template_id) ON DELETE CASCADE,
    enrolled_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    target_grade NUMERIC(3,2) NOT NULL DEFAULT 1.00
        CHECK (target_grade IN (1.00, 1.25, 1.50, 1.75, 2.00, 2.25, 2.50, 2.75, 3.00)),
    CONSTRAINT unique_student_template UNIQUE (user_id, template_id)
);

CREATE INDEX IF NOT EXISTS idx_enrolled_courses_user ON enrolled_courses(user_id);
CREATE INDEX IF NOT EXISTS idx_enrolled_courses_template ON enrolled_courses(template_id);

-- Already present in the live database; included here for fresh installations.
CREATE TABLE IF NOT EXISTS student_component_scores (
    score_id SERIAL PRIMARY KEY,
    enrollment_id INT NOT NULL REFERENCES enrolled_courses(enrollment_id) ON DELETE CASCADE,
    midterm_component_id INT REFERENCES midterms_template_components(id) ON DELETE CASCADE,
    final_component_id INT REFERENCES finals_template_components(id) ON DELETE CASCADE,
    assessment_title VARCHAR(100) NOT NULL CHECK (length(trim(assessment_title)) > 0),
    raw_score NUMERIC(9,2) NOT NULL CHECK (raw_score >= 0),
    total_items INT NOT NULL CHECK (total_items > 0),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT student_component_scores_check CHECK (raw_score <= total_items),
    CONSTRAINT student_component_scores_one_period CHECK ((midterm_component_id IS NOT NULL AND final_component_id IS NULL)
        OR (midterm_component_id IS NULL AND final_component_id IS NOT NULL))
);

CREATE INDEX IF NOT EXISTS idx_student_component_scores_midterm ON student_component_scores(midterm_component_id);
CREATE INDEX IF NOT EXISTS idx_student_component_scores_final ON student_component_scores(final_component_id);
CREATE INDEX IF NOT EXISTS idx_student_component_scores_enrollment ON student_component_scores(enrollment_id);

CREATE TABLE IF NOT EXISTS coursework_items (
    item_id SERIAL PRIMARY KEY,
    midterm_component_id INT REFERENCES midterms_template_components(id) ON DELETE CASCADE,
    final_component_id INT REFERENCES finals_template_components(id) ON DELETE CASCADE,
    item_title VARCHAR(100) NOT NULL,
    item_type VARCHAR(50) NOT NULL DEFAULT 'activity',
    max_score NUMERIC(7,2) NOT NULL DEFAULT 100.00 CHECK (max_score > 0),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT coursework_items_one_period CHECK ((midterm_component_id IS NOT NULL AND final_component_id IS NULL)
        OR (midterm_component_id IS NULL AND final_component_id IS NOT NULL))
);

CREATE INDEX IF NOT EXISTS idx_coursework_items_midterm ON coursework_items(midterm_component_id);
CREATE INDEX IF NOT EXISTS idx_coursework_items_final ON coursework_items(final_component_id);

-- The application uses its PHP session to scope queries to the signed-in user.
ALTER TABLE roles ENABLE ROW LEVEL SECURITY;
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE otp_verifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE grade_templates ENABLE ROW LEVEL SECURITY;
ALTER TABLE midterms_template_components ENABLE ROW LEVEL SECURITY;
ALTER TABLE finals_template_components ENABLE ROW LEVEL SECURITY;
ALTER TABLE enrolled_courses ENABLE ROW LEVEL SECURITY;
ALTER TABLE student_component_scores ENABLE ROW LEVEL SECURITY;
ALTER TABLE coursework_items ENABLE ROW LEVEL SECURITY;
