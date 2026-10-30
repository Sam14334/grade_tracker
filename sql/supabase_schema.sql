-- Supabase PostgreSQL Schema (Base Schema - No Data)
-- Matches the live public schema inspected on 2026-10-30.
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

CREATE TABLE IF NOT EXISTS template_components (
    id SERIAL PRIMARY KEY,
    template_id INT NOT NULL REFERENCES grade_templates(template_id) ON DELETE CASCADE,
    component_name VARCHAR(100) NOT NULL,
    weight INT NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_template_components_template ON template_components(template_id);

CREATE TABLE IF NOT EXISTS enrolled_courses (
    enrollment_id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    template_id INT NOT NULL REFERENCES grade_templates(template_id) ON DELETE CASCADE,
    enrolled_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_student_template UNIQUE (user_id, template_id)
);

CREATE INDEX IF NOT EXISTS idx_enrolled_courses_user ON enrolled_courses(user_id);
CREATE INDEX IF NOT EXISTS idx_enrolled_courses_template ON enrolled_courses(template_id);

-- The live database enables RLS on all six tables and defines no RLS policies.
ALTER TABLE roles ENABLE ROW LEVEL SECURITY;
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE otp_verifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE grade_templates ENABLE ROW LEVEL SECURITY;
ALTER TABLE template_components ENABLE ROW LEVEL SECURITY;
ALTER TABLE enrolled_courses ENABLE ROW LEVEL SECURITY;
