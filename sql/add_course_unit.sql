-- Run once on an existing Supabase database before deploying the PHP changes.
-- Existing templates receive 3.0 units; review these values for each course.
ALTER TABLE public.grade_templates
    ADD COLUMN IF NOT EXISTS course_unit NUMERIC(3,1) NOT NULL DEFAULT 3.0
    CHECK (course_unit > 0);
