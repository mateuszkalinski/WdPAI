--
-- PostgreSQL database dump
--

\restrict EwNhxGYZgHHqQVecJtfg8PUHK232817WRQUgV0kpbOf1g9drHg41SGqLfCsTLUf

-- Dumped from database version 16.14
-- Dumped by pg_dump version 16.11

-- Started on 2026-06-13 08:43:18 UTC

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- TOC entry 3 (class 3079 OID 16422)
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- TOC entry 3832 (class 0 OID 0)
-- Dependencies: 3
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


--
-- TOC entry 2 (class 3079 OID 16385)
-- Name: pgcrypto; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;


--
-- TOC entry 3833 (class 0 OID 0)
-- Dependencies: 2
-- Name: EXTENSION pgcrypto; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION pgcrypto IS 'cryptographic functions';


--
-- TOC entry 301 (class 1255 OID 16852)
-- Name: award_exercise_weight_badges(); Type: FUNCTION; Schema: public; Owner: docker
--

CREATE FUNCTION public.award_exercise_weight_badges() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    session_user_id INTEGER;
BEGIN
    SELECT user_id INTO session_user_id
    FROM workout_sessions
    WHERE id = NEW.workout_session_id;

    INSERT INTO user_badges (user_id, badge_id, source_workout_session_id, current_value)
    SELECT
        session_user_id,
        b.id,
        NEW.workout_session_id,
        NEW.weight_kg
    FROM badges b
    WHERE b.is_active = TRUE
      AND b.criteria_type = 'exercise_weight'
      AND b.exercise_id = NEW.exercise_id
      AND NEW.weight_kg >= b.target_value
    ON CONFLICT (user_id, badge_id) DO UPDATE
    SET current_value = GREATEST(user_badges.current_value, EXCLUDED.current_value);

    RETURN NEW;
END;
$$;


ALTER FUNCTION public.award_exercise_weight_badges() OWNER TO docker;

--
-- TOC entry 329 (class 1255 OID 16851)
-- Name: calculate_session_volume(integer); Type: FUNCTION; Schema: public; Owner: docker
--

CREATE FUNCTION public.calculate_session_volume(p_session_id integer) RETURNS numeric
    LANGUAGE sql STABLE
    AS $$
    SELECT COALESCE(SUM(weight_kg * reps), 0)::NUMERIC(12,2)
    FROM performed_sets
    WHERE workout_session_id = p_session_id;
$$;


ALTER FUNCTION public.calculate_session_volume(p_session_id integer) OWNER TO docker;

--
-- TOC entry 280 (class 1255 OID 16842)
-- Name: touch_updated_at(); Type: FUNCTION; Schema: public; Owner: docker
--

CREATE FUNCTION public.touch_updated_at() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.touch_updated_at() OWNER TO docker;

--
-- TOC entry 245 (class 1259 OID 16854)
-- Name: admin_user_overview; Type: VIEW; Schema: public; Owner: docker
--

CREATE VIEW public.admin_user_overview AS
SELECT
    NULL::integer AS id,
    NULL::character varying(50) AS username,
    NULL::character varying(255) AS email,
    NULL::character varying(30) AS role,
    NULL::boolean AS is_active,
    NULL::timestamp with time zone AS blocked_at,
    NULL::timestamp with time zone AS created_at,
    NULL::character varying(100) AS firstname,
    NULL::character varying(100) AS lastname,
    NULL::bigint AS finished_workouts;


ALTER VIEW public.admin_user_overview OWNER TO docker;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- TOC entry 242 (class 1259 OID 16758)
-- Name: badges; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.badges (
    id integer NOT NULL,
    created_by_user_id integer,
    exercise_id integer,
    muscle_group_id integer,
    name character varying(120) NOT NULL,
    slug character varying(140) NOT NULL,
    description text NOT NULL,
    icon character varying(60) DEFAULT 'military_tech'::character varying NOT NULL,
    criteria_type character varying(40) NOT NULL,
    target_value numeric(10,2) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT badges_criteria_type_check CHECK (((criteria_type)::text = ANY ((ARRAY['exercise_weight'::character varying, 'total_sessions'::character varying, 'total_volume'::character varying, 'muscle_sets'::character varying, 'custom'::character varying])::text[]))),
    CONSTRAINT badges_target_value_check CHECK ((target_value > (0)::numeric))
);


ALTER TABLE public.badges OWNER TO docker;

--
-- TOC entry 241 (class 1259 OID 16757)
-- Name: badges_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

ALTER TABLE public.badges ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.badges_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 225 (class 1259 OID 16564)
-- Name: equipment; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.equipment (
    id integer NOT NULL,
    name character varying(80) NOT NULL
);


ALTER TABLE public.equipment OWNER TO docker;

--
-- TOC entry 224 (class 1259 OID 16563)
-- Name: equipment_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

ALTER TABLE public.equipment ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.equipment_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 228 (class 1259 OID 16593)
-- Name: exercise_muscle_groups; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.exercise_muscle_groups (
    exercise_id integer NOT NULL,
    muscle_group_id integer NOT NULL,
    involvement character varying(20) DEFAULT 'primary'::character varying NOT NULL,
    CONSTRAINT exercise_muscle_groups_involvement_check CHECK (((involvement)::text = ANY ((ARRAY['primary'::character varying, 'secondary'::character varying, 'stabilizer'::character varying])::text[])))
);


ALTER TABLE public.exercise_muscle_groups OWNER TO docker;

--
-- TOC entry 227 (class 1259 OID 16572)
-- Name: exercises; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.exercises (
    id integer NOT NULL,
    equipment_id integer,
    name character varying(120) NOT NULL,
    slug character varying(140) NOT NULL,
    description text NOT NULL,
    technique_notes text,
    difficulty character varying(20) DEFAULT 'beginner'::character varying NOT NULL,
    video_url text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT exercises_difficulty_check CHECK (((difficulty)::text = ANY ((ARRAY['beginner'::character varying, 'intermediate'::character varying, 'advanced'::character varying])::text[])))
);


ALTER TABLE public.exercises OWNER TO docker;

--
-- TOC entry 240 (class 1259 OID 16730)
-- Name: performed_sets; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.performed_sets (
    id integer NOT NULL,
    workout_session_id integer NOT NULL,
    exercise_id integer NOT NULL,
    set_order integer NOT NULL,
    set_type character varying(20) DEFAULT 'working'::character varying NOT NULL,
    weight_kg numeric(7,2) DEFAULT 0 NOT NULL,
    reps integer NOT NULL,
    rpe numeric(3,1),
    note text,
    performed_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT performed_sets_reps_check CHECK ((reps > 0)),
    CONSTRAINT performed_sets_rpe_check CHECK (((rpe IS NULL) OR ((rpe >= (0)::numeric) AND (rpe <= (10)::numeric)))),
    CONSTRAINT performed_sets_set_order_check CHECK ((set_order > 0)),
    CONSTRAINT performed_sets_set_type_check CHECK (((set_type)::text = ANY ((ARRAY['warmup'::character varying, 'working'::character varying, 'drop'::character varying, 'failure'::character varying])::text[]))),
    CONSTRAINT performed_sets_weight_kg_check CHECK ((weight_kg >= (0)::numeric))
);


ALTER TABLE public.performed_sets OWNER TO docker;

--
-- TOC entry 234 (class 1259 OID 16651)
-- Name: workout_plan_exercises; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.workout_plan_exercises (
    id integer NOT NULL,
    workout_plan_day_id integer NOT NULL,
    exercise_id integer NOT NULL,
    exercise_order integer NOT NULL,
    target_sets integer NOT NULL,
    target_reps_min integer,
    target_reps_max integer,
    target_rpe numeric(3,1),
    rest_seconds integer DEFAULT 90 NOT NULL,
    notes text,
    CONSTRAINT workout_plan_exercises_check CHECK (((target_reps_min IS NULL) OR (target_reps_max IS NULL) OR (target_reps_min <= target_reps_max))),
    CONSTRAINT workout_plan_exercises_exercise_order_check CHECK ((exercise_order > 0)),
    CONSTRAINT workout_plan_exercises_rest_seconds_check CHECK ((rest_seconds > 0)),
    CONSTRAINT workout_plan_exercises_target_reps_max_check CHECK (((target_reps_max IS NULL) OR (target_reps_max > 0))),
    CONSTRAINT workout_plan_exercises_target_reps_min_check CHECK (((target_reps_min IS NULL) OR (target_reps_min > 0))),
    CONSTRAINT workout_plan_exercises_target_rpe_check CHECK (((target_rpe IS NULL) OR ((target_rpe >= (0)::numeric) AND (target_rpe <= (10)::numeric)))),
    CONSTRAINT workout_plan_exercises_target_sets_check CHECK ((target_sets > 0))
);


ALTER TABLE public.workout_plan_exercises OWNER TO docker;

--
-- TOC entry 248 (class 1259 OID 16869)
-- Name: exercise_usage_stats; Type: VIEW; Schema: public; Owner: docker
--

CREATE VIEW public.exercise_usage_stats AS
 WITH plan_usage AS (
         SELECT workout_plan_exercises.exercise_id,
            count(*) AS planned_occurrences
           FROM public.workout_plan_exercises
          GROUP BY workout_plan_exercises.exercise_id
        ), set_usage AS (
         SELECT performed_sets.exercise_id,
            count(DISTINCT performed_sets.workout_session_id) AS sessions_used,
            count(*) AS performed_sets,
            (COALESCE(sum((performed_sets.weight_kg * (performed_sets.reps)::numeric)), (0)::numeric))::numeric(12,2) AS total_volume_kg,
            max(performed_sets.weight_kg) AS max_weight_kg
           FROM public.performed_sets
          GROUP BY performed_sets.exercise_id
        )
 SELECT e.id AS exercise_id,
    e.name AS exercise_name,
    eq.name AS equipment,
    COALESCE(pu.planned_occurrences, (0)::bigint) AS planned_occurrences,
    COALESCE(su.sessions_used, (0)::bigint) AS sessions_used,
    COALESCE(su.performed_sets, (0)::bigint) AS performed_sets,
    (COALESCE(su.total_volume_kg, (0)::numeric))::numeric(12,2) AS total_volume_kg,
    su.max_weight_kg
   FROM (((public.exercises e
     LEFT JOIN public.equipment eq ON ((eq.id = e.equipment_id)))
     LEFT JOIN plan_usage pu ON ((pu.exercise_id = e.id)))
     LEFT JOIN set_usage su ON ((su.exercise_id = e.id)));


ALTER VIEW public.exercise_usage_stats OWNER TO docker;

--
-- TOC entry 226 (class 1259 OID 16571)
-- Name: exercises_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

ALTER TABLE public.exercises ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.exercises_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 253 (class 1259 OID 25101)
-- Name: login_attempts; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.login_attempts (
    id integer NOT NULL,
    email character varying(100) NOT NULL,
    ip_address inet,
    reason character varying(40) NOT NULL,
    attempted_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.login_attempts OWNER TO docker;

--
-- TOC entry 252 (class 1259 OID 25100)
-- Name: login_attempts_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

ALTER TABLE public.login_attempts ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.login_attempts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 223 (class 1259 OID 16554)
-- Name: muscle_groups; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.muscle_groups (
    id integer NOT NULL,
    name character varying(80) NOT NULL,
    description text
);


ALTER TABLE public.muscle_groups OWNER TO docker;

--
-- TOC entry 222 (class 1259 OID 16553)
-- Name: muscle_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

ALTER TABLE public.muscle_groups ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.muscle_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 239 (class 1259 OID 16729)
-- Name: performed_sets_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

ALTER TABLE public.performed_sets ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.performed_sets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 218 (class 1259 OID 16504)
-- Name: roles; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.roles (
    id integer NOT NULL,
    name character varying(30) NOT NULL,
    description text
);


ALTER TABLE public.roles OWNER TO docker;

--
-- TOC entry 217 (class 1259 OID 16503)
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

ALTER TABLE public.roles ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 244 (class 1259 OID 16791)
-- Name: user_badges; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.user_badges (
    id integer NOT NULL,
    user_id integer NOT NULL,
    badge_id integer NOT NULL,
    source_workout_session_id integer,
    current_value numeric(10,2) DEFAULT 0 NOT NULL,
    awarded_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT user_badges_current_value_check CHECK ((current_value >= (0)::numeric))
);


ALTER TABLE public.user_badges OWNER TO docker;

--
-- TOC entry 220 (class 1259 OID 16514)
-- Name: users; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.users (
    id integer NOT NULL,
    role_id integer NOT NULL,
    username character varying(50) NOT NULL,
    email character varying(255) NOT NULL,
    password text NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    blocked_at timestamp with time zone,
    blocked_reason text,
    last_login_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.users OWNER TO docker;

--
-- TOC entry 238 (class 1259 OID 16700)
-- Name: workout_sessions; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.workout_sessions (
    id integer NOT NULL,
    user_id integer NOT NULL,
    workout_plan_id integer,
    workout_plan_day_id integer,
    name character varying(140) NOT NULL,
    status character varying(20) DEFAULT 'planned'::character varying NOT NULL,
    started_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    finished_at timestamp with time zone,
    session_rpe numeric(3,1),
    notes text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT workout_sessions_check CHECK (((finished_at IS NULL) OR (finished_at >= started_at))),
    CONSTRAINT workout_sessions_session_rpe_check CHECK (((session_rpe IS NULL) OR ((session_rpe >= (0)::numeric) AND (session_rpe <= (10)::numeric)))),
    CONSTRAINT workout_sessions_status_check CHECK (((status)::text = ANY ((ARRAY['planned'::character varying, 'in_progress'::character varying, 'finished'::character varying, 'cancelled'::character varying])::text[])))
);


ALTER TABLE public.workout_sessions OWNER TO docker;

--
-- TOC entry 249 (class 1259 OID 16874)
-- Name: user_badge_overview; Type: VIEW; Schema: public; Owner: docker
--

CREATE VIEW public.user_badge_overview AS
 SELECT u.id AS user_id,
    u.username,
    b.id AS badge_id,
    b.name AS badge_name,
    b.criteria_type,
    b.target_value,
    ub.current_value,
    ub.awarded_at,
    ws.name AS source_session
   FROM (((public.user_badges ub
     JOIN public.users u ON ((u.id = ub.user_id)))
     JOIN public.badges b ON ((b.id = ub.badge_id)))
     LEFT JOIN public.workout_sessions ws ON ((ws.id = ub.source_workout_session_id)));


ALTER VIEW public.user_badge_overview OWNER TO docker;

--
-- TOC entry 243 (class 1259 OID 16790)
-- Name: user_badges_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

ALTER TABLE public.user_badges ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.user_badges_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 221 (class 1259 OID 16535)
-- Name: user_profiles; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.user_profiles (
    user_id integer NOT NULL,
    firstname character varying(100) NOT NULL,
    lastname character varying(100) NOT NULL,
    bio text,
    birth_date date,
    height_cm numeric(5,2),
    body_weight_kg numeric(5,2),
    experience_level character varying(20) DEFAULT 'beginner'::character varying NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT user_profiles_body_weight_kg_check CHECK (((body_weight_kg IS NULL) OR (body_weight_kg > (0)::numeric))),
    CONSTRAINT user_profiles_experience_level_check CHECK (((experience_level)::text = ANY ((ARRAY['beginner'::character varying, 'intermediate'::character varying, 'advanced'::character varying])::text[]))),
    CONSTRAINT user_profiles_height_cm_check CHECK (((height_cm IS NULL) OR (height_cm > (0)::numeric)))
);


ALTER TABLE public.user_profiles OWNER TO docker;

--
-- TOC entry 246 (class 1259 OID 16859)
-- Name: user_training_summary; Type: VIEW; Schema: public; Owner: docker
--

CREATE VIEW public.user_training_summary AS
 SELECT u.id AS user_id,
    u.username,
    count(DISTINCT ws.id) FILTER (WHERE ((ws.status)::text = 'finished'::text)) AS finished_sessions,
    (COALESCE(sum((ps.weight_kg * (ps.reps)::numeric)), (0)::numeric))::numeric(12,2) AS total_volume_kg,
    count(ps.id) AS total_sets,
    round(avg(ps.rpe), 2) AS average_set_rpe,
    max(ws.started_at) FILTER (WHERE ((ws.status)::text = 'finished'::text)) AS last_workout_at
   FROM ((public.users u
     LEFT JOIN public.workout_sessions ws ON ((ws.user_id = u.id)))
     LEFT JOIN public.performed_sets ps ON ((ps.workout_session_id = ws.id)))
  GROUP BY u.id, u.username;


ALTER VIEW public.user_training_summary OWNER TO docker;

--
-- TOC entry 236 (class 1259 OID 16679)
-- Name: user_workout_plans; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.user_workout_plans (
    id integer NOT NULL,
    user_id integer NOT NULL,
    workout_plan_id integer NOT NULL,
    custom_name character varying(140),
    is_active boolean DEFAULT true NOT NULL,
    assigned_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.user_workout_plans OWNER TO docker;

--
-- TOC entry 235 (class 1259 OID 16678)
-- Name: user_workout_plans_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

ALTER TABLE public.user_workout_plans ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.user_workout_plans_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 219 (class 1259 OID 16513)
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

ALTER TABLE public.users ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 247 (class 1259 OID 16864)
-- Name: weekly_muscle_group_summary; Type: VIEW; Schema: public; Owner: docker
--

CREATE VIEW public.weekly_muscle_group_summary AS
 SELECT u.id AS user_id,
    u.username,
    (date_trunc('week'::text, ws.started_at))::date AS week_start,
    mg.id AS muscle_group_id,
    mg.name AS muscle_group,
    count(ps.id) AS sets_count,
    (COALESCE(sum((ps.weight_kg * (ps.reps)::numeric)), (0)::numeric))::numeric(12,2) AS volume_kg,
    round(avg(ps.rpe), 2) AS average_rpe
   FROM (((((public.users u
     JOIN public.workout_sessions ws ON ((ws.user_id = u.id)))
     JOIN public.performed_sets ps ON ((ps.workout_session_id = ws.id)))
     JOIN public.exercises e ON ((e.id = ps.exercise_id)))
     JOIN public.exercise_muscle_groups emg ON (((emg.exercise_id = e.id) AND ((emg.involvement)::text = 'primary'::text))))
     JOIN public.muscle_groups mg ON ((mg.id = emg.muscle_group_id)))
  WHERE ((ws.status)::text = 'finished'::text)
  GROUP BY u.id, u.username, ((date_trunc('week'::text, ws.started_at))::date), mg.id, mg.name;


ALTER VIEW public.weekly_muscle_group_summary OWNER TO docker;

--
-- TOC entry 232 (class 1259 OID 16632)
-- Name: workout_plan_days; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.workout_plan_days (
    id integer NOT NULL,
    workout_plan_id integer NOT NULL,
    name character varying(120) NOT NULL,
    day_of_week smallint,
    day_order integer NOT NULL,
    notes text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT workout_plan_days_day_of_week_check CHECK (((day_of_week >= 1) AND (day_of_week <= 7))),
    CONSTRAINT workout_plan_days_day_order_check CHECK ((day_order > 0))
);


ALTER TABLE public.workout_plan_days OWNER TO docker;

--
-- TOC entry 231 (class 1259 OID 16631)
-- Name: workout_plan_days_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

ALTER TABLE public.workout_plan_days ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.workout_plan_days_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 233 (class 1259 OID 16650)
-- Name: workout_plan_exercises_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

ALTER TABLE public.workout_plan_exercises ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.workout_plan_exercises_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 230 (class 1259 OID 16611)
-- Name: workout_plans; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.workout_plans (
    id integer NOT NULL,
    owner_user_id integer,
    name character varying(140) NOT NULL,
    description text,
    goal character varying(40) DEFAULT 'hypertrophy'::character varying NOT NULL,
    level character varying(20) DEFAULT 'beginner'::character varying NOT NULL,
    is_template boolean DEFAULT false NOT NULL,
    is_public boolean DEFAULT false NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT workout_plans_goal_check CHECK (((goal)::text = ANY ((ARRAY['strength'::character varying, 'hypertrophy'::character varying, 'endurance'::character varying, 'general'::character varying])::text[]))),
    CONSTRAINT workout_plans_level_check CHECK (((level)::text = ANY ((ARRAY['beginner'::character varying, 'intermediate'::character varying, 'advanced'::character varying])::text[])))
);


ALTER TABLE public.workout_plans OWNER TO docker;

--
-- TOC entry 229 (class 1259 OID 16610)
-- Name: workout_plans_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

ALTER TABLE public.workout_plans ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.workout_plans_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 251 (class 1259 OID 25072)
-- Name: workout_session_plan_skips; Type: TABLE; Schema: public; Owner: docker
--

CREATE TABLE public.workout_session_plan_skips (
    id integer NOT NULL,
    workout_session_id integer NOT NULL,
    workout_plan_exercise_id integer NOT NULL,
    exercise_id integer NOT NULL,
    skipped_sets integer DEFAULT 1 NOT NULL,
    note text,
    skipped_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT workout_session_plan_skips_skipped_sets_check CHECK ((skipped_sets > 0))
);


ALTER TABLE public.workout_session_plan_skips OWNER TO docker;

--
-- TOC entry 250 (class 1259 OID 25071)
-- Name: workout_session_plan_skips_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

ALTER TABLE public.workout_session_plan_skips ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.workout_session_plan_skips_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 237 (class 1259 OID 16699)
-- Name: workout_sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

ALTER TABLE public.workout_sessions ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.workout_sessions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 3820 (class 0 OID 16758)
-- Dependencies: 242
-- Data for Name: badges; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.badges (id, created_by_user_id, exercise_id, muscle_group_id, name, slug, description, icon, criteria_type, target_value, is_active, created_at, updated_at) FROM stdin;
1	1	1	\N	Pierwsza stówka na klatę	pierwsza-stowka-na-klate	Przyznawana za wykonanie serii wyciskania sztangi leżąc z ciężarem co najmniej 100 kg.	workspace_premium	exercise_weight	100.00	t	2026-06-11 21:27:14.79639+00	2026-06-11 21:27:14.79639+00
2	1	10	\N	Przysiad 120 kg	przysiad-120-kg	Przyznawana za wykonanie serii przysiadu ze sztangą z ciężarem co najmniej 120 kg.	military_tech	exercise_weight	120.00	t	2026-06-11 21:27:14.79639+00	2026-06-11 21:27:14.79639+00
3	1	16	\N	Martwy ciąg 150 kg	martwy-ciag-150-kg	Przyznawana za wykonanie serii martwego ciągu klasycznego z ciężarem co najmniej 150 kg.	trophy	exercise_weight	150.00	t	2026-06-11 21:27:14.79639+00	2026-06-11 21:27:14.79639+00
4	1	\N	\N	Pierwszy trening	pierwszy-trening	Przyznawana za zakończenie pierwszej sesji treningowej.	flag	total_sessions	1.00	t	2026-06-11 21:27:14.79639+00	2026-06-11 21:27:14.79639+00
5	1	\N	\N	10 treningów	10-treningow	Przyznawana za zakończenie 10 sesji treningowych.	event_available	total_sessions	10.00	t	2026-06-11 21:27:14.79639+00	2026-06-11 21:27:14.79639+00
6	1	\N	\N	Objętość 10000 kg	objetosc-10000-kg	Przyznawana za uzbieranie 10000 kg całkowitej objętości.	monitoring	total_volume	10000.00	t	2026-06-11 21:27:14.79639+00	2026-06-11 21:27:14.79639+00
7	1	\N	2	Plecy pod kontrolą	plecy-pod-kontrola	Przyznawana za wykonanie wyznaczonej liczby serii na plecy.	fitness_center	muscle_sets	20.00	t	2026-06-11 21:27:14.79639+00	2026-06-11 21:27:14.79639+00
\.


--
-- TOC entry 3803 (class 0 OID 16564)
-- Dependencies: 225
-- Data for Name: equipment; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.equipment (id, name) FROM stdin;
1	Sztanga
2	Hantle
3	Maszyna
4	Wyciąg
5	Masa ciała
6	Ławka
7	Kettlebell
\.


--
-- TOC entry 3806 (class 0 OID 16593)
-- Dependencies: 228
-- Data for Name: exercise_muscle_groups; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.exercise_muscle_groups (exercise_id, muscle_group_id, involvement) FROM stdin;
1	1	primary
1	5	secondary
1	3	secondary
2	1	primary
2	3	secondary
3	1	primary
3	5	secondary
4	3	primary
4	5	secondary
5	3	primary
6	2	primary
6	4	secondary
7	2	primary
7	4	secondary
8	2	primary
8	4	secondary
9	2	primary
10	6	primary
10	8	secondary
11	7	primary
11	8	secondary
12	6	primary
12	8	secondary
13	6	primary
13	8	secondary
14	7	primary
15	9	primary
16	2	primary
16	7	secondary
16	8	secondary
17	8	primary
18	4	primary
19	4	primary
20	5	primary
21	5	primary
22	10	primary
23	10	primary
24	3	primary
24	2	secondary
25	5	primary
25	1	secondary
\.


--
-- TOC entry 3805 (class 0 OID 16572)
-- Dependencies: 227
-- Data for Name: exercises; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.exercises (id, equipment_id, name, slug, description, technique_notes, difficulty, video_url, is_active, created_at, updated_at) FROM stdin;
1	1	Wyciskanie sztangi leżąc	wyciskanie-sztangi-lezac	Podstawowe ćwiczenie na klatkę piersiową wykonywane na ławce poziomej.	Utrzymuj łopatki ściągnięte, kontroluj tor sztangi i nie odrywaj pośladków od ławki.	intermediate	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
2	2	Wyciskanie hantli na skosie dodatnim	wyciskanie-hantli-skos-dodatni	Ćwiczenie akcentujące górną część klatki piersiowej.	Prowadź hantle stabilnie i nie skracaj zakresu ruchu.	intermediate	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
3	5	Pompki	pompki	Wielostawowe ćwiczenie z masą ciała na klatkę i triceps.	Trzymaj ciało w jednej linii i kontroluj zejście.	beginner	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
4	1	Wyciskanie żołnierskie	wyciskanie-zolnierskie	Wyciskanie sztangi nad głowę rozwijające barki i stabilizację.	Nie wyginaj nadmiernie odcinka lędźwiowego.	intermediate	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
5	2	Unoszenie hantli bokiem	unoszenie-hantli-bokiem	Izolowane ćwiczenie na boczny akton barków.	Prowadź łokcie lekko ugięte i unikaj szarpania.	beginner	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
6	5	Podciąganie nachwytem	podciaganie-nachwytem	Ćwiczenie na plecy i biceps wykonywane na drążku.	Rozpoczynaj z pełnego zwisu i prowadź klatkę do drążka.	intermediate	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
7	1	Wiosłowanie sztangą	wioslowanie-sztanga	Wielostawowe ćwiczenie na grubość pleców.	Utrzymuj neutralne plecy i prowadź sztangę w stronę bioder.	intermediate	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
8	4	Ściąganie drążka do klatki	sciaganie-drazka-do-klatki	Maszynowa alternatywa dla podciągania.	Ściągaj łopatki i nie bujaj tułowiem.	beginner	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
9	4	Wiosłowanie siedząc na wyciągu	wioslowanie-siedzac-wyciag	Ćwiczenie na środek pleców z linką wyciągu.	Zatrzymaj ruch na moment przy tułowiu.	beginner	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
10	1	Przysiad ze sztangą	przysiad-ze-sztanga	Podstawowe ćwiczenie na nogi i pośladki.	Utrzymuj napięty brzuch, kolana prowadź zgodnie z linią stóp.	intermediate	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
11	1	Martwy ciąg rumuński	martwy-ciag-rumunski	Ćwiczenie na tylną taśmę uda i pośladki.	Cofaj biodra, zachowaj neutralne plecy i czuj rozciągnięcie dwugłowych.	intermediate	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
12	3	Wypychanie na suwnicy	wypychanie-na-suwnicy	Ćwiczenie maszynowe na uda i pośladki.	Nie blokuj agresywnie kolan w górze ruchu.	beginner	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
13	2	Wykroki z hantlami	wykroki-z-hantlami	Ćwiczenie unilateralne na nogi i pośladki.	Kontroluj krok i utrzymuj stabilne kolano.	beginner	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
14	3	Uginanie nóg leżąc	uginanie-nog-lezac	Izolowane ćwiczenie na tył uda.	Nie odrywaj bioder od podparcia.	beginner	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
15	3	Wspięcia na palce	wspiecia-na-palce	Ćwiczenie na mięśnie łydek.	Zatrzymaj ruch na górze i kontroluj zejście.	beginner	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
16	1	Martwy ciąg klasyczny	martwy-ciag-klasyczny	Ciężkie ćwiczenie wielostawowe angażujące całe ciało.	Napnij brzuch przed oderwaniem sztangi i utrzymuj ją blisko ciała.	advanced	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
17	1	Hip thrust	hip-thrust	Ćwiczenie ukierunkowane na pośladki.	Zatrzymaj biodra w pełnym wyproście bez przeprostu lędźwi.	beginner	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
18	2	Uginanie ramion z hantlami	uginanie-ramion-z-hantlami	Podstawowe ćwiczenie na biceps.	Nie bujaj tułowiem i kontroluj opuszczanie.	beginner	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
19	2	Uginanie młotkowe	uginanie-mlotkowe	Wariant uginania ramion mocniej angażujący ramienny.	Trzymaj neutralny chwyt przez cały ruch.	beginner	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
20	4	Prostowanie ramion na wyciągu	prostowanie-ramion-na-wyciagu	Izolowane ćwiczenie na triceps.	Łokcie trzymaj blisko tułowia.	beginner	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
21	1	Francuskie wyciskanie leżąc	francuskie-wyciskanie-lezac	Ćwiczenie izolujące triceps.	Kontroluj łokcie i nie opuszczaj ciężaru zbyt gwałtownie.	intermediate	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
22	5	Plank	plank	Ćwiczenie izometryczne na stabilizację tułowia.	Nie opuszczaj bioder i utrzymuj napięcie brzucha.	beginner	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
23	4	Spięcia brzucha na wyciągu	spiecia-brzucha-na-wyciagu	Ćwiczenie na mięśnie brzucha z obciążeniem.	Zwijaj tułów, nie ciągnij rękami.	beginner	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
24	4	Face pull	face-pull	Ćwiczenie na tylny akton barków i zdrowie obręczy barkowej.	Prowadź linkę do twarzy i rotuj ramiona na zewnątrz.	beginner	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
25	5	Dipy na poręczach	dipy-na-poreczach	Ćwiczenie na triceps i dolną część klatki.	Kontroluj głębokość i nie zapadaj barków.	intermediate	\N	t	2026-06-11 21:27:14.650666+00	2026-06-11 21:27:14.650666+00
\.


--
-- TOC entry 3826 (class 0 OID 25101)
-- Dependencies: 253
-- Data for Name: login_attempts; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.login_attempts (id, email, ip_address, reason, attempted_at) FROM stdin;
\.


--
-- TOC entry 3801 (class 0 OID 16554)
-- Dependencies: 223
-- Data for Name: muscle_groups; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.muscle_groups (id, name, description) FROM stdin;
1	Klatka piersiowa	Mięśnie piersiowe większe i mniejsze.
2	Plecy	Najszerszy grzbietu, czworoboczne i mięśnie środka pleców.
3	Barki	Przedni, boczny i tylny akton mięśnia naramiennego.
4	Biceps	Mięsień dwugłowy ramienia.
5	Triceps	Mięsień trójgłowy ramienia.
6	Czworogłowe uda	Przednia część uda.
7	Dwugłowe uda	Tylna część uda.
8	Pośladki	Mięśnie pośladkowe.
9	Łydki	Mięśnie podudzia.
10	Core	Mięśnie brzucha i stabilizacja tułowia.
\.


--
-- TOC entry 3818 (class 0 OID 16730)
-- Dependencies: 240
-- Data for Name: performed_sets; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.performed_sets (id, workout_session_id, exercise_id, set_order, set_type, weight_kg, reps, rpe, note, performed_at) FROM stdin;
1	1	1	3	working	77.50	8	9.0	Ostatnia seria ciężka.	2026-06-11 21:27:14.810704+00
2	1	1	2	working	80.00	8	8.5	\N	2026-06-11 21:27:14.810704+00
3	1	1	1	working	100.00	1	8.5	Pierwsza stówka na klatę.	2026-06-11 21:27:14.810704+00
4	1	2	2	working	30.00	9	8.5	\N	2026-06-11 21:27:14.810704+00
5	1	2	1	working	30.00	10	8.0	\N	2026-06-11 21:27:14.810704+00
6	1	5	2	working	10.00	14	8.0	\N	2026-06-11 21:27:14.810704+00
7	1	5	1	working	10.00	15	8.0	\N	2026-06-11 21:27:14.810704+00
8	1	20	2	working	35.00	12	8.0	\N	2026-06-11 21:27:14.810704+00
9	1	20	1	working	35.00	12	8.0	\N	2026-06-11 21:27:14.810704+00
10	2	6	3	working	0.00	6	9.0	\N	2026-06-11 21:27:14.810704+00
11	2	6	2	working	0.00	7	8.5	\N	2026-06-11 21:27:14.810704+00
12	2	6	1	working	0.00	8	8.0	\N	2026-06-11 21:27:14.810704+00
13	2	7	2	working	70.00	9	8.0	\N	2026-06-11 21:27:14.810704+00
14	2	7	1	working	70.00	10	8.0	\N	2026-06-11 21:27:14.810704+00
15	2	18	2	working	14.00	10	8.5	\N	2026-06-11 21:27:14.810704+00
16	2	18	1	working	14.00	12	8.0	\N	2026-06-11 21:27:14.810704+00
17	2	24	2	working	25.00	15	7.5	\N	2026-06-11 21:27:14.810704+00
18	2	24	1	working	25.00	15	7.0	\N	2026-06-11 21:27:14.810704+00
19	3	25	1	working	42.50	8	8.0	Smoke API set	2026-06-11 21:31:33.831431+00
54	38	25	1	working	50.00	8	8.0	Badge smoke set	2026-06-12 09:19:58.786757+00
55	39	10	1	working	42.50	6	8.0	codex-plan-link-test	2026-06-12 09:44:01.885625+00
56	40	10	1	working	42.50	6	8.0	codex-plan-link-test	2026-06-12 09:46:59.484385+00
57	41	11	1	working	47.50	8	8.0	codex-skip-weight-test	2026-06-12 10:09:35.739947+00
58	42	12	1	working	40.00	10	8.0	\N	2026-06-12 10:15:35.596307+00
59	42	12	2	working	40.00	10	8.0	\N	2026-06-12 10:15:44.779091+00
60	42	12	3	working	40.00	10	8.0	\N	2026-06-12 10:15:48.853629+00
61	42	2	1	working	40.00	8	8.0	\N	2026-06-12 10:16:03.187054+00
62	42	2	2	working	40.00	8	8.0	\N	2026-06-12 10:16:04.928541+00
63	42	2	3	working	40.00	8	8.0	\N	2026-06-12 10:16:06.025857+00
64	42	8	1	working	40.00	10	8.0	\N	2026-06-12 10:16:08.158711+00
65	42	8	2	working	40.00	10	8.0	\N	2026-06-12 10:16:11.837217+00
66	42	8	3	working	40.00	10	8.0	\N	2026-06-12 10:16:17.463112+00
67	43	12	1	warmup	40.00	10	8.5	test	2026-06-12 10:19:13.961772+00
68	43	12	2	working	40.00	10	8.0	sasdsa	2026-06-12 10:19:35.51082+00
69	43	12	3	working	40.00	10	8.0	\N	2026-06-12 10:19:37.88029+00
70	43	2	1	working	40.00	8	8.0	\N	2026-06-12 10:19:43.83663+00
71	43	2	2	working	40.00	8	8.0	\N	2026-06-12 10:19:44.232167+00
72	43	2	3	working	40.00	8	8.0	\N	2026-06-12 10:19:44.800482+00
73	43	8	1	working	40.00	10	8.0	\N	2026-06-12 10:19:46.437851+00
74	43	8	2	working	40.00	10	8.0	\N	2026-06-12 10:19:50.983987+00
75	44	10	1	working	40.00	6	8.0	\N	2026-06-12 10:47:10.644106+00
76	44	10	2	working	40.00	6	8.0	\N	2026-06-12 10:47:12.123826+00
77	44	10	3	working	40.00	6	8.0	\N	2026-06-12 10:47:13.356576+00
78	44	1	1	working	40.00	8	8.0	\N	2026-06-12 10:47:15.229527+00
79	44	1	2	working	40.00	8	8.0	\N	2026-06-12 10:47:19.787228+00
80	44	1	3	working	40.00	8	8.0	\N	2026-06-12 10:47:20.543803+00
81	44	7	1	working	40.00	8	8.0	\N	2026-06-12 10:47:25.854893+00
82	44	7	2	working	40.00	8	8.0	\N	2026-06-12 10:47:26.977189+00
83	44	7	3	working	40.00	8	8.0	\N	2026-06-12 10:47:27.576826+00
84	44	7	4	working	40.00	8	8.0	\N	2026-06-12 10:47:28.513046+00
85	44	7	5	working	40.00	8	8.0	\N	2026-06-12 10:47:29.212011+00
86	44	7	6	working	40.00	8	8.0	\N	2026-06-12 10:47:29.878205+00
87	44	7	7	working	40.00	8	8.0	\N	2026-06-12 10:47:30.606466+00
88	44	7	8	working	40.00	8	8.0	\N	2026-06-12 10:47:31.26696+00
89	44	7	9	working	40.00	8	8.0	\N	2026-06-12 10:47:31.780465+00
90	44	7	10	working	40.00	8	8.0	\N	2026-06-12 10:47:57.401309+00
91	44	7	11	working	40.00	8	8.0	\N	2026-06-12 10:47:57.688303+00
92	44	7	12	working	40.00	8	8.0	\N	2026-06-12 10:47:57.853247+00
93	44	7	13	working	40.00	8	8.0	\N	2026-06-12 10:47:58.006587+00
94	44	7	14	working	40.00	8	8.0	\N	2026-06-12 10:47:58.155133+00
95	44	7	15	working	40.00	8	8.0	\N	2026-06-12 10:47:58.287809+00
96	44	7	16	working	40.00	8	8.0	\N	2026-06-12 10:47:58.618086+00
97	45	11	1	working	40.00	8	8.0	\N	2026-06-12 10:53:08.835929+00
98	45	11	2	working	40.00	8	8.0	\N	2026-06-12 10:53:26.173896+00
99	47	10	1	working	40.00	6	8.0	\N	2026-06-12 11:27:23.823732+00
100	47	10	2	working	40.00	6	8.0	\N	2026-06-12 11:27:25.729786+00
101	47	10	3	working	40.00	6	8.0	\N	2026-06-12 11:27:26.352538+00
102	47	10	4	working	40.00	6	8.0	\N	2026-06-12 11:27:27.702986+00
103	47	1	1	working	40.00	8	8.0	\N	2026-06-12 11:27:30.501488+00
104	47	1	2	working	40.00	8	8.0	\N	2026-06-12 11:27:31.081313+00
105	47	1	3	working	40.00	8	8.0	\N	2026-06-12 11:27:31.766213+00
106	47	7	1	working	40.00	8	8.0	\N	2026-06-12 11:27:34.627841+00
107	47	7	2	working	40.00	8	8.0	\N	2026-06-12 11:27:35.888732+00
108	47	7	3	working	40.00	8	8.0	\N	2026-06-12 11:27:38.923513+00
109	48	11	1	working	40.00	8	8.0	zapas	2026-06-12 11:28:13.24314+00
110	49	10	1	working	120.00	6	8.0	\N	2026-06-12 11:31:43.640111+00
111	49	10	2	working	120.00	6	8.0	\N	2026-06-12 11:31:52.092123+00
112	49	10	3	working	120.00	6	8.0	\N	2026-06-12 11:31:52.618434+00
113	50	10	1	working	40.00	8	8.0	\N	2026-06-12 11:32:47.98327+00
114	50	10	2	working	40.00	8	8.0	\N	2026-06-12 11:32:49.387016+00
115	50	10	3	working	40.00	8	8.0	\N	2026-06-12 11:32:49.959764+00
116	50	10	4	working	40.00	8	8.0	\N	2026-06-12 11:32:50.515154+00
117	50	10	5	working	40.00	8	8.0	\N	2026-06-12 11:32:51.354431+00
118	50	10	6	working	40.00	8	8.0	\N	2026-06-12 11:34:42.826181+00
119	51	8	1	working	55.00	10	8.0	asdadssadsadsaddas	2026-06-12 11:38:11.485929+00
120	51	8	2	working	55.00	10	8.0	\N	2026-06-12 11:38:20.069335+00
121	53	6	1	working	40.00	6	8.0	\N	2026-06-12 11:42:16.76034+00
122	53	6	2	working	40.00	6	8.0	\N	2026-06-12 11:42:16.874884+00
123	53	6	3	working	40.00	6	8.0	\N	2026-06-12 11:42:17.03473+00
124	53	7	1	working	40.00	8	8.0	\N	2026-06-12 11:42:18.859797+00
125	53	7	2	working	40.00	8	8.0	\N	2026-06-12 11:42:19.094126+00
126	53	24	1	working	40.00	12	7.0	\N	2026-06-12 11:42:21.231689+00
127	53	24	2	working	40.00	12	7.0	\N	2026-06-12 11:42:21.434598+00
128	53	18	1	working	40.00	10	8.0	\N	2026-06-12 11:42:23.20144+00
129	53	18	2	working	40.00	10	8.0	\N	2026-06-12 11:42:23.376548+00
130	53	18	3	working	40.00	10	8.0	\N	2026-06-12 11:42:23.531245+00
131	54	10	1	working	40.00	6	\N	\N	2026-06-12 12:01:39.443815+00
132	54	10	2	working	40.00	6	6.0	\N	2026-06-12 12:01:41.72914+00
133	54	10	3	working	40.00	6	0.0	\N	2026-06-12 12:01:57.078986+00
134	54	10	4	working	40.00	6	\N	\N	2026-06-12 12:02:04.156202+00
135	56	6	1	working	40.00	6	\N	\N	2026-06-12 12:30:20.547192+00
136	58	11	1	working	40.00	8	\N	\N	2026-06-12 12:35:14.428787+00
137	58	11	2	working	40.00	8	\N	\N	2026-06-12 12:35:15.026938+00
138	58	11	3	working	40.00	8	\N	\N	2026-06-12 12:35:15.877176+00
139	58	4	1	working	40.00	6	\N	\N	2026-06-12 12:35:28.790762+00
140	58	4	2	working	40.00	6	\N	\N	2026-06-12 12:35:30.088102+00
141	58	4	3	working	40.00	6	\N	\N	2026-06-12 12:35:31.329242+00
142	58	4	4	working	40.00	6	\N	\N	2026-06-12 12:35:32.61861+00
143	58	4	5	working	40.00	6	\N	\N	2026-06-12 12:35:33.55027+00
144	58	6	1	working	40.00	6	\N	\N	2026-06-12 12:35:36.121888+00
145	58	6	2	working	40.00	6	\N	\N	2026-06-12 12:35:36.794065+00
146	58	6	3	working	40.00	6	\N	\N	2026-06-12 12:35:37.438529+00
147	59	12	1	working	40.00	10	5.0	\N	2026-06-12 12:35:59.541198+00
148	59	12	2	working	40.00	10	\N	\N	2026-06-12 12:36:02.784803+00
149	59	12	3	working	40.00	10	0.0	\N	2026-06-12 12:36:05.460332+00
150	59	12	4	working	40.00	10	\N	\N	2026-06-12 12:36:14.988149+00
151	59	2	1	working	40.00	8	\N	\N	2026-06-12 12:36:16.643882+00
152	59	2	2	working	40.00	8	7.0	\N	2026-06-12 12:36:20.525155+00
153	59	8	1	working	40.00	10	\N	\N	2026-06-12 12:36:43.586508+00
154	59	8	2	working	40.00	10	\N	\N	2026-06-12 12:36:44.176535+00
155	60	10	1	working	40.00	6	\N	\N	2026-06-13 07:58:01.789964+00
156	60	10	2	working	40.00	6	\N	\N	2026-06-13 07:58:02.336965+00
157	60	10	3	working	40.00	6	\N	\N	2026-06-13 07:58:02.838238+00
158	60	1	1	working	40.00	8	\N	\N	2026-06-13 07:58:04.025363+00
159	60	1	2	working	40.00	8	\N	\N	2026-06-13 07:58:04.419226+00
160	60	1	3	working	40.00	8	\N	\N	2026-06-13 07:58:04.834303+00
161	60	7	1	working	40.00	8	\N	\N	2026-06-13 07:58:05.9333+00
162	60	7	2	working	40.00	8	\N	\N	2026-06-13 07:58:06.221348+00
163	60	7	3	working	40.00	8	\N	\N	2026-06-13 07:58:06.77864+00
164	60	7	4	working	40.00	8	\N	\N	2026-06-13 07:58:07.6808+00
165	61	10	1	working	40.00	6	\N	\N	2026-06-13 08:23:09.98822+00
166	61	10	2	working	40.00	6	\N	\N	2026-06-13 08:23:10.738534+00
167	61	10	3	working	40.00	6	\N	\N	2026-06-13 08:23:11.009665+00
\.


--
-- TOC entry 3796 (class 0 OID 16504)
-- Dependencies: 218
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.roles (id, name, description) FROM stdin;
1	admin	Administrator aplikacji z dostępem do zarządzania użytkownikami.
2	user	Standardowy użytkownik prowadzący dziennik treningowy.
\.


--
-- TOC entry 3822 (class 0 OID 16791)
-- Dependencies: 244
-- Data for Name: user_badges; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.user_badges (id, user_id, badge_id, source_workout_session_id, current_value, awarded_at) FROM stdin;
1	2	1	1	100.00	2026-06-11 21:27:14.810704+00
2	2	4	41	7.00	2026-06-12 09:19:58.890228+00
23	5	2	49	120.00	2026-06-12 11:31:43.640111+00
56	6	4	59	3.00	2026-06-12 12:33:12.249396+00
6	5	4	60	16.00	2026-06-12 10:16:34.650886+00
33	5	5	60	16.00	2026-06-12 11:41:47.526004+00
9	5	6	60	29820.00	2026-06-12 10:52:55.110621+00
10	5	7	60	36.00	2026-06-12 10:52:55.110621+00
63	7	4	61	1.00	2026-06-13 08:23:13.228143+00
\.


--
-- TOC entry 3799 (class 0 OID 16535)
-- Dependencies: 221
-- Data for Name: user_profiles; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.user_profiles (user_id, firstname, lastname, bio, birth_date, height_cm, body_weight_kg, experience_level, created_at, updated_at) FROM stdin;
1	Admin	Systemowy	Konto administracyjne do testowania panelu.	\N	\N	\N	advanced	2026-06-11 21:27:14.620661+00	2026-06-11 21:27:14.620661+00
2	Jan	Trenujący	Trening hipertroficzny 4 razy w tygodniu.	\N	181.00	84.50	intermediate	2026-06-11 21:27:14.620661+00	2026-06-11 21:27:14.620661+00
3	Jan	Zablokowany	Konto do sprawdzania blokady logowania.	\N	176.00	79.00	beginner	2026-06-11 21:27:14.620661+00	2026-06-11 21:27:14.620661+00
5	mati	kal		\N	\N	\N	beginner	2026-06-12 10:14:29.336598+00	2026-06-12 10:14:29.336598+00
6	wdpai	wdpai		\N	\N	\N	beginner	2026-06-12 12:31:08.642125+00	2026-06-12 12:31:08.642125+00
7	aaa	aaa		\N	\N	\N	beginner	2026-06-13 08:22:35.024415+00	2026-06-13 08:22:35.024415+00
\.


--
-- TOC entry 3814 (class 0 OID 16679)
-- Dependencies: 236
-- Data for Name: user_workout_plans; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.user_workout_plans (id, user_id, workout_plan_id, custom_name, is_active, assigned_at, updated_at) FROM stdin;
1	2	2	PPL - aktualny plan	t	2026-06-11 21:27:14.784264+00	2026-06-11 21:27:14.784264+00
19	7	1	\N	f	2026-06-13 08:22:46.483409+00	2026-06-13 08:22:50.310015+00
20	7	10	Mój FBW 3 dni	t	2026-06-13 08:22:50.310015+00	2026-06-13 08:22:50.310015+00
9	6	1	\N	t	2026-06-12 12:32:23.817381+00	2026-06-12 12:32:23.817381+00
7	5	5	Mój FBW 3 dni	f	2026-06-12 11:28:57.569576+00	2026-06-13 08:18:51.072629+00
11	5	6	\N	f	2026-06-13 07:59:22.877549+00	2026-06-13 08:18:51.072629+00
13	5	7	Mój FBW 3 dni	f	2026-06-13 08:00:54.464146+00	2026-06-13 08:18:51.072629+00
15	5	8	Mój FBW 3 dni	f	2026-06-13 08:07:54.570354+00	2026-06-13 08:18:51.072629+00
6	5	1	\N	f	2026-06-13 08:12:47.269654+00	2026-06-13 08:18:51.072629+00
8	5	2	\N	f	2026-06-13 08:18:49.607579+00	2026-06-13 08:18:51.072629+00
18	5	9	Mój Push Pull Legs	t	2026-06-13 08:18:51.072629+00	2026-06-13 08:18:51.072629+00
\.


--
-- TOC entry 3798 (class 0 OID 16514)
-- Dependencies: 220
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.users (id, role_id, username, email, password, is_active, blocked_at, blocked_reason, last_login_at, created_at, updated_at) FROM stdin;
3	2	blocked_user	blocked@wdpai.com	$2a$06$LF.20ESnp1Tk.s7bil89AOkXuS6L4k1dcTu0sv1ByR0ENec79aFpK	f	2026-06-11 21:27:14.614773+00	Konto testowo zablokowane przez administratora.	\N	2026-06-11 21:27:14.571625+00	2026-06-11 21:27:14.614773+00
7	2	aaa	a@a.pl	$2y$10$VcQAJ/ddYoHsLeNCMr5S0ukCiL.9zY1pwKSCGpHLwERVaeJHaho.W	t	\N	\N	2026-06-13 08:34:57.315279+00	2026-06-13 08:22:35.024415+00	2026-06-13 08:34:57.315279+00
2	2	user	user@wdpai.com	$2a$06$WkR8OVC9ANA9xBAEyZNGTueKCLW33z8bzRKBmmG9WcaKb2FRjM92y	t	\N	\N	2026-06-13 08:35:45.240428+00	2026-06-11 21:27:14.571625+00	2026-06-13 08:35:45.240428+00
1	1	admin	admin@hipertrof.io	$2a$06$CAzTgmJ0TmEugEY2GfVRT.7rdNKaFJ8jmAnVYoaE3Rq1LGjXQ5P6e	t	\N	\N	2026-06-11 22:00:39.116228+00	2026-06-11 21:27:14.571625+00	2026-06-11 22:00:39.116228+00
5	2	matias	test@test.pl	$2y$10$8Sib5iTThRfYdKJIqvxlRO9DqM90RcJdiz8A12bN4iwPBHqkq66R.	t	\N	\N	2026-06-12 11:26:47.339981+00	2026-06-12 10:14:29.336598+00	2026-06-12 11:26:47.339981+00
6	2	wdpai	email@email.com	$2y$10$.mVENW7Osy28sW9ZsCt5x.kCyNZjzBOQwVfI8fV6Mpo4ozfjTubj2	t	\N	\N	2026-06-12 12:31:17.427352+00	2026-06-12 12:31:08.642125+00	2026-06-12 12:31:17.427352+00
\.


--
-- TOC entry 3810 (class 0 OID 16632)
-- Dependencies: 232
-- Data for Name: workout_plan_days; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.workout_plan_days (id, workout_plan_id, name, day_of_week, day_order, notes, created_at, updated_at) FROM stdin;
1	1	FBW A	1	1	Pierwszy trening tygodnia.	2026-06-11 21:27:14.751429+00	2026-06-11 21:27:14.751429+00
2	1	FBW B	3	2	Drugi trening tygodnia.	2026-06-11 21:27:14.751429+00	2026-06-11 21:27:14.751429+00
3	1	FBW C	5	3	Trzeci trening tygodnia.	2026-06-11 21:27:14.751429+00	2026-06-11 21:27:14.751429+00
4	2	Push	1	1	Klatka, barki i triceps.	2026-06-11 21:27:14.751429+00	2026-06-11 21:27:14.751429+00
5	2	Pull	3	2	Plecy i biceps.	2026-06-11 21:27:14.751429+00	2026-06-11 21:27:14.751429+00
6	2	Legs	5	3	Nogi i core.	2026-06-11 21:27:14.751429+00	2026-06-11 21:27:14.751429+00
7	3	Push A	1	1	Mocniejszy akcent na klatkę.	2026-06-11 21:27:14.751429+00	2026-06-11 21:27:14.751429+00
8	3	Pull A	3	2	Objętość na plecy.	2026-06-11 21:27:14.751429+00	2026-06-11 21:27:14.751429+00
9	3	Legs A	5	3	Przysiad i tył uda.	2026-06-11 21:27:14.751429+00	2026-06-11 21:27:14.751429+00
10	4	Push	1	1	Klatka, barki i triceps.	2026-06-11 21:39:23.824686+00	2026-06-11 21:39:23.824686+00
11	4	Pull	3	2	Plecy i biceps.	2026-06-11 21:39:23.824686+00	2026-06-11 21:39:23.824686+00
12	4	Legs	5	3	Nogi i core.	2026-06-11 21:39:23.824686+00	2026-06-11 21:39:23.824686+00
13	5	FBW A	1	1	Pierwszy trening tygodnia.	2026-06-12 11:28:57.569576+00	2026-06-12 11:28:57.569576+00
14	5	FBW B	3	2	Drugi trening tygodnia.	2026-06-12 11:28:57.569576+00	2026-06-12 11:28:57.569576+00
15	5	FBW C	5	3	Trzeci trening tygodnia.	2026-06-12 11:28:57.569576+00	2026-06-12 11:28:57.569576+00
16	6	a	\N	1	\N	2026-06-13 07:59:22.877549+00	2026-06-13 07:59:22.877549+00
17	6	b	\N	2	\N	2026-06-13 07:59:22.877549+00	2026-06-13 07:59:22.877549+00
18	6	Dzien 3	\N	3	\N	2026-06-13 07:59:22.877549+00	2026-06-13 07:59:22.877549+00
19	7	FBW A	1	1	Pierwszy trening tygodnia.	2026-06-13 08:00:54.464146+00	2026-06-13 08:00:54.464146+00
20	7	FBW B	3	2	Drugi trening tygodnia.	2026-06-13 08:00:54.464146+00	2026-06-13 08:00:54.464146+00
21	7	FBW C	5	3	Trzeci trening tygodnia.	2026-06-13 08:00:54.464146+00	2026-06-13 08:00:54.464146+00
22	8	FBW A	1	1	Pierwszy trening tygodnia.	2026-06-13 08:07:54.570354+00	2026-06-13 08:07:54.570354+00
23	8	FBW B	3	2	Drugi trening tygodnia.	2026-06-13 08:07:54.570354+00	2026-06-13 08:07:54.570354+00
24	8	FBW C	5	3	Trzeci trening tygodnia.	2026-06-13 08:07:54.570354+00	2026-06-13 08:07:54.570354+00
25	9	Push	1	1	Klatka, barki i triceps.	2026-06-13 08:18:51.072629+00	2026-06-13 08:18:51.072629+00
26	9	Pull	3	2	Plecy i biceps.	2026-06-13 08:18:51.072629+00	2026-06-13 08:18:51.072629+00
27	9	Legs	5	3	Nogi i core.	2026-06-13 08:18:51.072629+00	2026-06-13 08:18:51.072629+00
28	10	FBW A	1	1	Pierwszy trening tygodnia.	2026-06-13 08:22:50.310015+00	2026-06-13 08:22:50.310015+00
29	10	FBW B	3	2	Drugi trening tygodnia.	2026-06-13 08:22:50.310015+00	2026-06-13 08:22:50.310015+00
30	10	FBW C	5	3	Trzeci trening tygodnia.	2026-06-13 08:22:50.310015+00	2026-06-13 08:22:50.310015+00
\.


--
-- TOC entry 3812 (class 0 OID 16651)
-- Dependencies: 234
-- Data for Name: workout_plan_exercises; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.workout_plan_exercises (id, workout_plan_day_id, exercise_id, exercise_order, target_sets, target_reps_min, target_reps_max, target_rpe, rest_seconds, notes) FROM stdin;
1	1	10	1	3	6	8	8.0	150	\N
2	1	1	2	3	8	10	8.0	120	\N
3	1	7	3	3	8	10	8.0	120	\N
4	2	11	1	3	8	10	8.0	150	\N
5	2	4	2	3	6	8	8.0	120	\N
6	2	6	3	3	6	10	8.0	120	\N
7	3	12	1	3	10	12	8.0	120	\N
8	3	2	2	3	8	12	8.0	120	\N
9	3	8	3	3	10	12	8.0	90	\N
10	4	1	1	4	6	8	8.0	150	\N
11	4	2	2	3	8	10	8.0	120	\N
12	4	5	3	4	12	15	8.0	75	\N
13	4	20	4	3	10	15	8.0	75	\N
14	5	6	1	4	6	10	8.0	150	\N
15	5	7	2	4	8	10	8.0	120	\N
16	5	24	3	3	12	15	7.0	75	\N
17	5	18	4	3	10	12	8.0	75	\N
18	6	10	1	4	6	8	8.0	150	\N
19	6	11	2	3	8	10	8.0	120	\N
20	6	13	3	3	10	12	8.0	90	Na nogę.
21	6	22	4	3	30	60	7.0	60	Czas w sekundach wpisywany w polu powtórzeń.
23	10	2	2	3	8	10	8.0	120	\N
24	10	5	3	4	12	15	8.0	75	\N
25	10	20	4	3	10	15	8.0	75	\N
26	11	6	1	4	6	10	8.0	150	\N
27	11	7	2	4	8	10	8.0	120	\N
28	11	24	3	3	12	15	7.0	75	\N
29	11	18	4	3	10	12	8.0	75	\N
30	12	10	1	4	6	8	8.0	150	\N
31	12	11	2	3	8	10	8.0	120	\N
32	12	13	3	3	10	12	8.0	90	Na nogę.
33	12	22	4	3	30	60	7.0	60	Czas w sekundach wpisywany w polu powtórzeń.
34	10	25	5	3	8	10	8.0	90	\N
35	11	25	5	3	8	10	8.0	90	\N
36	13	10	1	3	6	8	8.0	150	\N
37	13	1	2	3	8	10	8.0	120	\N
38	13	7	3	3	8	10	8.0	120	\N
39	14	11	1	3	8	10	8.0	150	\N
40	14	4	2	3	6	8	8.0	120	\N
41	14	6	3	3	6	10	8.0	120	\N
42	15	12	1	3	10	12	8.0	120	\N
43	15	2	2	3	8	12	8.0	120	\N
44	15	8	3	3	10	12	8.0	90	\N
45	15	25	4	3	8	10	8.0	90	\N
46	13	25	4	3	8	10	8.0	90	\N
47	14	10	4	3	8	10	8.0	90	\N
48	19	10	1	3	6	8	8.0	150	\N
49	19	1	2	3	8	10	8.0	120	\N
50	19	7	3	3	8	10	8.0	120	\N
51	20	11	1	3	8	10	8.0	150	\N
52	20	4	2	3	6	8	8.0	120	\N
54	21	12	1	3	10	12	8.0	120	\N
55	21	2	2	3	8	12	8.0	120	\N
56	21	8	3	3	10	12	8.0	90	\N
53	20	6	3	3	6	11	\N	120	\N
58	22	1	2	3	8	10	8.0	120	\N
59	22	7	3	3	8	10	8.0	120	\N
60	23	11	1	3	8	10	8.0	150	\N
61	23	4	2	3	6	8	8.0	120	\N
62	23	6	3	3	6	10	8.0	120	\N
63	24	12	1	3	10	12	8.0	120	\N
64	24	2	2	3	8	12	8.0	120	\N
65	24	8	3	3	10	12	8.0	90	\N
57	22	10	1	3	6	8	\N	150	\N
66	25	1	1	4	6	8	8.0	150	\N
67	25	2	2	3	8	10	8.0	120	\N
68	25	5	3	4	12	15	8.0	75	\N
69	25	20	4	3	10	15	8.0	75	\N
70	26	6	1	4	6	10	8.0	150	\N
71	26	7	2	4	8	10	8.0	120	\N
72	26	24	3	3	12	15	7.0	75	\N
73	26	18	4	3	10	12	8.0	75	\N
74	27	10	1	4	6	8	8.0	150	\N
75	27	11	2	3	8	10	8.0	120	\N
76	27	13	3	3	10	12	8.0	90	Na nogę.
77	27	22	4	3	30	60	7.0	60	Czas w sekundach wpisywany w polu powtórzeń.
78	28	10	1	3	6	8	8.0	150	\N
79	28	1	2	3	8	10	8.0	120	\N
80	28	7	3	3	8	10	8.0	120	\N
81	29	11	1	3	8	10	8.0	150	\N
82	29	4	2	3	6	8	8.0	120	\N
83	29	6	3	3	6	10	8.0	120	\N
84	30	12	1	3	10	12	8.0	120	\N
85	30	2	2	3	8	12	8.0	120	\N
86	30	8	3	3	10	12	8.0	90	\N
\.


--
-- TOC entry 3808 (class 0 OID 16611)
-- Dependencies: 230
-- Data for Name: workout_plans; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.workout_plans (id, owner_user_id, name, description, goal, level, is_template, is_public, created_at, updated_at) FROM stdin;
1	\N	FBW 3 dni	Gotowy plan całego ciała wykonywany trzy razy w tygodniu.	general	beginner	t	t	2026-06-11 21:27:14.736638+00	2026-06-11 21:27:14.736638+00
2	\N	Push Pull Legs	Gotowy plan hipertroficzny z podziałem na wypychanie, przyciąganie i nogi.	hypertrophy	intermediate	t	t	2026-06-11 21:27:14.736638+00	2026-06-11 21:27:14.736638+00
3	2	Mój plan - hipertrofia	Własny plan użytkownika oparty o Push Pull Legs.	hypertrophy	intermediate	f	f	2026-06-11 21:27:14.736638+00	2026-06-11 21:27:14.736638+00
4	\N	Mój Push Pull Legs 11.06	Gotowy plan hipertroficzny z podziałem na wypychanie, przyciąganie i nogi.	hypertrophy	intermediate	f	f	2026-06-11 21:39:23.824686+00	2026-06-11 22:03:39.646857+00
5	5	Mój FBW 3 dni 12.06	Gotowy plan całego ciała wykonywany trzy razy w tygodniu.	general	beginner	f	f	2026-06-12 11:28:57.569576+00	2026-06-12 11:28:57.569576+00
6	5	test	Plan utworzony od zera.	general	beginner	f	f	2026-06-13 07:59:22.877549+00	2026-06-13 07:59:22.877549+00
7	5	Mój FBW 3 dni 13.06	Gotowy plan całego ciała wykonywany trzy razy w tygodniu.	general	beginner	f	f	2026-06-13 08:00:54.464146+00	2026-06-13 08:00:54.464146+00
8	5	Mój FBW 3 dni 13.06	Gotowy plan całego ciała wykonywany trzy razy w tygodniu.	general	beginner	f	f	2026-06-13 08:07:54.570354+00	2026-06-13 08:07:54.570354+00
9	5	Mój Push Pull Legs 13.06	Gotowy plan hipertroficzny z podziałem na wypychanie, przyciąganie i nogi.	hypertrophy	intermediate	f	f	2026-06-13 08:18:51.072629+00	2026-06-13 08:18:51.072629+00
10	7	Mój FBW 3 dni 13.06	Gotowy plan całego ciała wykonywany trzy razy w tygodniu.	general	beginner	f	f	2026-06-13 08:22:50.310015+00	2026-06-13 08:22:50.310015+00
\.


--
-- TOC entry 3824 (class 0 OID 25072)
-- Dependencies: 251
-- Data for Name: workout_session_plan_skips; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.workout_session_plan_skips (id, workout_session_id, workout_plan_exercise_id, exercise_id, skipped_sets, note, skipped_at) FROM stdin;
1	41	18	10	4	Pominieto cwiczenie	2026-06-12 10:09:35.606899+00
3	42	9	8	1	Pominieto serie	2026-06-12 10:16:09.940017+00
4	43	9	8	2	Pominieto cwiczenie	2026-06-12 10:19:47.144032+00
5	50	39	11	3	Przejscie do nastepnego cwiczenia	2026-06-12 11:32:20.478602+00
6	50	40	4	3	Przejscie do nastepnego cwiczenia	2026-06-12 11:32:21.675383+00
7	50	41	6	3	Przejscie do nastepnego cwiczenia	2026-06-12 11:32:24.121119+00
8	51	42	12	3	Przejscie do nastepnego cwiczenia	2026-06-12 11:35:00.490527+00
9	51	43	2	3	Przejscie do nastepnego cwiczenia	2026-06-12 11:35:09.106362+00
10	51	44	8	1	Przejscie do nastepnego cwiczenia	2026-06-12 11:39:21.925343+00
11	51	45	25	3	Przejscie do nastepnego cwiczenia	2026-06-12 11:39:54.277285+00
12	53	14	6	1	Przejscie do nastepnego cwiczenia	2026-06-12 11:42:18.148894+00
13	53	15	7	2	Przejscie do nastepnego cwiczenia	2026-06-12 11:42:19.870941+00
14	53	16	24	1	Przejscie do nastepnego cwiczenia	2026-06-12 11:42:22.345859+00
15	54	19	11	3	Przejscie do nastepnego cwiczenia	2026-06-12 12:02:07.511977+00
16	54	20	13	3	Przejscie do nastepnego cwiczenia	2026-06-12 12:02:08.629087+00
17	55	10	1	4	Przejscie do nastepnego cwiczenia	2026-06-12 12:02:37.515401+00
18	55	11	2	3	Przejscie do nastepnego cwiczenia	2026-06-12 12:02:42.370796+00
19	55	12	5	4	Przejscie do nastepnego cwiczenia	2026-06-12 12:02:46.895069+00
20	56	14	6	3	Przejscie do nastepnego cwiczenia	2026-06-12 12:30:23.847835+00
21	56	15	7	4	Przejscie do nastepnego cwiczenia	2026-06-12 12:30:24.769507+00
22	56	16	24	3	Przejscie do nastepnego cwiczenia	2026-06-12 12:30:25.684643+00
23	57	1	10	3	Przejscie do nastepnego cwiczenia	2026-06-12 12:33:06.277693+00
24	57	2	1	3	Przejscie do nastepnego cwiczenia	2026-06-12 12:33:06.950958+00
25	59	8	2	1	Przejscie do nastepnego cwiczenia	2026-06-12 12:36:23.743147+00
26	61	79	1	3	Przejscie do nastepnego cwiczenia	2026-06-13 08:23:12.02527+00
\.


--
-- TOC entry 3816 (class 0 OID 16700)
-- Dependencies: 238
-- Data for Name: workout_sessions; Type: TABLE DATA; Schema: public; Owner: docker
--

COPY public.workout_sessions (id, user_id, workout_plan_id, workout_plan_day_id, name, status, started_at, finished_at, session_rpe, notes, created_at, updated_at) FROM stdin;
1	2	2	4	Push - trening testowy	finished	2026-06-06 21:27:14.810704+00	2026-06-06 22:39:14.810704+00	8.0	Dobra energia, lekki zapas na wyciskaniu.	2026-06-11 21:27:14.810704+00	2026-06-11 21:27:14.810704+00
2	2	2	5	Pull - trening testowy	finished	2026-06-09 21:27:14.810704+00	2026-06-09 22:32:14.810704+00	7.5	Podciąganie weszło lepiej niż tydzień temu.	2026-06-11 21:27:14.810704+00	2026-06-11 21:27:14.810704+00
3	2	2	4	Push	finished	2026-06-11 21:31:33.552834+00	2026-06-11 21:31:34.45375+00	8.0	Smoke API finish	2026-06-11 21:31:33.552834+00	2026-06-11 21:31:34.45375+00
38	2	2	6	Legs	finished	2026-06-12 09:19:58.529686+00	2026-06-12 09:19:58.890228+00	8.0	Badge smoke finish	2026-06-12 09:19:58.529686+00	2026-06-12 09:19:58.890228+00
39	2	2	6	Legs	finished	2026-06-12 09:44:01.475353+00	2026-06-12 09:44:02.001475+00	7.0	codex plan link smoke	2026-06-12 09:44:01.475353+00	2026-06-12 09:44:02.001475+00
40	2	2	6	Legs	finished	2026-06-12 09:46:59.100327+00	2026-06-12 09:46:59.614779+00	7.0	codex plan link smoke	2026-06-12 09:46:59.100327+00	2026-06-12 09:46:59.614779+00
41	2	2	6	Legs	finished	2026-06-12 10:09:34.956524+00	2026-06-12 10:09:35.888649+00	7.0	codex skip and weight smoke	2026-06-12 10:09:34.956524+00	2026-06-12 10:09:35.888649+00
42	5	1	3	FBW C	finished	2026-06-12 10:15:35.596307+00	2026-06-12 10:16:34.650886+00	\N	\N	2026-06-12 10:15:35.596307+00	2026-06-12 10:16:34.650886+00
43	5	1	3	FBW C	finished	2026-06-12 10:19:13.961772+00	2026-06-12 10:46:59.212048+00	\N	\N	2026-06-12 10:19:13.961772+00	2026-06-12 10:46:59.212048+00
44	5	1	1	FBW A	finished	2026-06-12 10:47:07.888539+00	2026-06-12 10:52:55.110621+00	\N	\N	2026-06-12 10:47:07.888539+00	2026-06-12 10:52:55.110621+00
45	5	1	2	FBW B	finished	2026-06-12 10:53:08.835929+00	2026-06-12 11:26:52.844281+00	\N	\N	2026-06-12 10:53:08.835929+00	2026-06-12 11:26:52.844281+00
46	5	1	3	FBW C	finished	2026-06-12 11:26:59.78641+00	2026-06-12 11:27:03.158472+00	\N	\N	2026-06-12 11:26:59.78641+00	2026-06-12 11:27:03.158472+00
47	5	1	1	FBW A	finished	2026-06-12 11:27:08.197692+00	2026-06-12 11:27:49.015637+00	\N	\N	2026-06-12 11:27:08.197692+00	2026-06-12 11:27:49.015637+00
48	5	1	2	FBW B	finished	2026-06-12 11:28:04.137217+00	2026-06-12 11:28:15.277392+00	\N	\N	2026-06-12 11:28:04.137217+00	2026-06-12 11:28:15.277392+00
49	5	5	13	FBW A	finished	2026-06-12 11:29:16.784328+00	2026-06-12 11:31:57.409676+00	\N	\N	2026-06-12 11:29:16.784328+00	2026-06-12 11:31:57.409676+00
50	5	5	14	FBW B	finished	2026-06-12 11:32:17.145825+00	2026-06-12 11:34:52.234993+00	\N	cos tam	2026-06-12 11:32:17.145825+00	2026-06-12 11:34:52.234993+00
51	5	5	15	FBW C	finished	2026-06-12 11:34:55.682202+00	2026-06-12 11:41:47.526004+00	\N	\N	2026-06-12 11:34:55.682202+00	2026-06-12 11:41:47.526004+00
52	5	2	4	Push	finished	2026-06-12 11:41:52.890839+00	2026-06-12 11:41:59.435157+00	\N	\N	2026-06-12 11:41:52.890839+00	2026-06-12 11:41:59.435157+00
53	5	2	5	Pull	finished	2026-06-12 11:42:12.131941+00	2026-06-12 11:42:27.380558+00	\N	\N	2026-06-12 11:42:12.131941+00	2026-06-12 11:42:27.380558+00
54	5	2	6	Legs	finished	2026-06-12 12:01:16.29802+00	2026-06-12 12:02:15.149497+00	\N	\N	2026-06-12 12:01:16.29802+00	2026-06-12 12:02:15.149497+00
55	5	2	4	Push	finished	2026-06-12 12:02:37.515401+00	2026-06-12 12:02:52.612775+00	\N	bylo git dodac obciazenie	2026-06-12 12:02:37.515401+00	2026-06-12 12:02:52.612775+00
56	5	2	5	Pull	finished	2026-06-12 12:30:20.547192+00	2026-06-12 12:30:35.02671+00	\N	\N	2026-06-12 12:30:20.547192+00	2026-06-12 12:30:35.02671+00
57	6	1	1	FBW A	finished	2026-06-12 12:33:06.277693+00	2026-06-12 12:33:12.249396+00	\N	\N	2026-06-12 12:33:06.277693+00	2026-06-12 12:33:12.249396+00
58	6	1	2	FBW B	finished	2026-06-12 12:35:14.428787+00	2026-06-12 12:35:41.469525+00	\N	\N	2026-06-12 12:35:14.428787+00	2026-06-12 12:35:41.469525+00
59	6	1	3	FBW C	finished	2026-06-12 12:35:59.541198+00	2026-06-12 12:36:47.221313+00	\N	nauczyc sie techniki na suwnicy, zwiekszyc obciazenie na sciaganiu drazka	2026-06-12 12:35:59.541198+00	2026-06-12 12:36:47.221313+00
60	5	1	1	FBW A	finished	2026-06-13 07:58:01.789964+00	2026-06-13 07:58:09.53916+00	\N	\N	2026-06-13 07:58:01.789964+00	2026-06-13 07:58:09.53916+00
61	7	10	28	FBW A	finished	2026-06-13 08:23:09.98822+00	2026-06-13 08:23:13.228143+00	\N	\N	2026-06-13 08:23:09.98822+00	2026-06-13 08:23:13.228143+00
\.


--
-- TOC entry 3834 (class 0 OID 0)
-- Dependencies: 241
-- Name: badges_id_seq; Type: SEQUENCE SET; Schema: public; Owner: docker
--

SELECT pg_catalog.setval('public.badges_id_seq', 7, true);


--
-- TOC entry 3835 (class 0 OID 0)
-- Dependencies: 224
-- Name: equipment_id_seq; Type: SEQUENCE SET; Schema: public; Owner: docker
--

SELECT pg_catalog.setval('public.equipment_id_seq', 7, true);


--
-- TOC entry 3836 (class 0 OID 0)
-- Dependencies: 226
-- Name: exercises_id_seq; Type: SEQUENCE SET; Schema: public; Owner: docker
--

SELECT pg_catalog.setval('public.exercises_id_seq', 25, true);


--
-- TOC entry 3837 (class 0 OID 0)
-- Dependencies: 252
-- Name: login_attempts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: docker
--

SELECT pg_catalog.setval('public.login_attempts_id_seq', 33, true);


--
-- TOC entry 3838 (class 0 OID 0)
-- Dependencies: 222
-- Name: muscle_groups_id_seq; Type: SEQUENCE SET; Schema: public; Owner: docker
--

SELECT pg_catalog.setval('public.muscle_groups_id_seq', 10, true);


--
-- TOC entry 3839 (class 0 OID 0)
-- Dependencies: 239
-- Name: performed_sets_id_seq; Type: SEQUENCE SET; Schema: public; Owner: docker
--

SELECT pg_catalog.setval('public.performed_sets_id_seq', 167, true);


--
-- TOC entry 3840 (class 0 OID 0)
-- Dependencies: 217
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: docker
--

SELECT pg_catalog.setval('public.roles_id_seq', 2, true);


--
-- TOC entry 3841 (class 0 OID 0)
-- Dependencies: 243
-- Name: user_badges_id_seq; Type: SEQUENCE SET; Schema: public; Owner: docker
--

SELECT pg_catalog.setval('public.user_badges_id_seq', 63, true);


--
-- TOC entry 3842 (class 0 OID 0)
-- Dependencies: 235
-- Name: user_workout_plans_id_seq; Type: SEQUENCE SET; Schema: public; Owner: docker
--

SELECT pg_catalog.setval('public.user_workout_plans_id_seq', 20, true);


--
-- TOC entry 3843 (class 0 OID 0)
-- Dependencies: 219
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: docker
--

SELECT pg_catalog.setval('public.users_id_seq', 7, true);


--
-- TOC entry 3844 (class 0 OID 0)
-- Dependencies: 231
-- Name: workout_plan_days_id_seq; Type: SEQUENCE SET; Schema: public; Owner: docker
--

SELECT pg_catalog.setval('public.workout_plan_days_id_seq', 30, true);


--
-- TOC entry 3845 (class 0 OID 0)
-- Dependencies: 233
-- Name: workout_plan_exercises_id_seq; Type: SEQUENCE SET; Schema: public; Owner: docker
--

SELECT pg_catalog.setval('public.workout_plan_exercises_id_seq', 86, true);


--
-- TOC entry 3846 (class 0 OID 0)
-- Dependencies: 229
-- Name: workout_plans_id_seq; Type: SEQUENCE SET; Schema: public; Owner: docker
--

SELECT pg_catalog.setval('public.workout_plans_id_seq', 10, true);


--
-- TOC entry 3847 (class 0 OID 0)
-- Dependencies: 250
-- Name: workout_session_plan_skips_id_seq; Type: SEQUENCE SET; Schema: public; Owner: docker
--

SELECT pg_catalog.setval('public.workout_session_plan_skips_id_seq', 26, true);


--
-- TOC entry 3848 (class 0 OID 0)
-- Dependencies: 237
-- Name: workout_sessions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: docker
--

SELECT pg_catalog.setval('public.workout_sessions_id_seq', 61, true);


--
-- TOC entry 3593 (class 2606 OID 16772)
-- Name: badges badges_name_key; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.badges
    ADD CONSTRAINT badges_name_key UNIQUE (name);


--
-- TOC entry 3595 (class 2606 OID 16770)
-- Name: badges badges_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.badges
    ADD CONSTRAINT badges_pkey PRIMARY KEY (id);


--
-- TOC entry 3597 (class 2606 OID 16774)
-- Name: badges badges_slug_key; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.badges
    ADD CONSTRAINT badges_slug_key UNIQUE (slug);


--
-- TOC entry 3539 (class 2606 OID 16570)
-- Name: equipment equipment_name_key; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_name_key UNIQUE (name);


--
-- TOC entry 3542 (class 2606 OID 16568)
-- Name: equipment equipment_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_pkey PRIMARY KEY (id);


--
-- TOC entry 3555 (class 2606 OID 16599)
-- Name: exercise_muscle_groups exercise_muscle_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.exercise_muscle_groups
    ADD CONSTRAINT exercise_muscle_groups_pkey PRIMARY KEY (exercise_id, muscle_group_id);


--
-- TOC entry 3547 (class 2606 OID 16585)
-- Name: exercises exercises_name_key; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.exercises
    ADD CONSTRAINT exercises_name_key UNIQUE (name);


--
-- TOC entry 3550 (class 2606 OID 16583)
-- Name: exercises exercises_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.exercises
    ADD CONSTRAINT exercises_pkey PRIMARY KEY (id);


--
-- TOC entry 3552 (class 2606 OID 16587)
-- Name: exercises exercises_slug_key; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.exercises
    ADD CONSTRAINT exercises_slug_key UNIQUE (slug);


--
-- TOC entry 3612 (class 2606 OID 25108)
-- Name: login_attempts login_attempts_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.login_attempts
    ADD CONSTRAINT login_attempts_pkey PRIMARY KEY (id);


--
-- TOC entry 3534 (class 2606 OID 16562)
-- Name: muscle_groups muscle_groups_name_key; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.muscle_groups
    ADD CONSTRAINT muscle_groups_name_key UNIQUE (name);


--
-- TOC entry 3537 (class 2606 OID 16560)
-- Name: muscle_groups muscle_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.muscle_groups
    ADD CONSTRAINT muscle_groups_pkey PRIMARY KEY (id);


--
-- TOC entry 3584 (class 2606 OID 16744)
-- Name: performed_sets performed_sets_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.performed_sets
    ADD CONSTRAINT performed_sets_pkey PRIMARY KEY (id);


--
-- TOC entry 3588 (class 2606 OID 16746)
-- Name: performed_sets performed_sets_workout_session_id_exercise_id_set_order_key; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.performed_sets
    ADD CONSTRAINT performed_sets_workout_session_id_exercise_id_set_order_key UNIQUE (workout_session_id, exercise_id, set_order);


--
-- TOC entry 3519 (class 2606 OID 16512)
-- Name: roles roles_name_key; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_key UNIQUE (name);


--
-- TOC entry 3521 (class 2606 OID 16510)
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- TOC entry 3600 (class 2606 OID 16798)
-- Name: user_badges user_badges_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.user_badges
    ADD CONSTRAINT user_badges_pkey PRIMARY KEY (id);


--
-- TOC entry 3602 (class 2606 OID 16800)
-- Name: user_badges user_badges_user_id_badge_id_key; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.user_badges
    ADD CONSTRAINT user_badges_user_id_badge_id_key UNIQUE (user_id, badge_id);


--
-- TOC entry 3532 (class 2606 OID 16547)
-- Name: user_profiles user_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.user_profiles
    ADD CONSTRAINT user_profiles_pkey PRIMARY KEY (user_id);


--
-- TOC entry 3572 (class 2606 OID 16686)
-- Name: user_workout_plans user_workout_plans_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.user_workout_plans
    ADD CONSTRAINT user_workout_plans_pkey PRIMARY KEY (id);


--
-- TOC entry 3575 (class 2606 OID 16688)
-- Name: user_workout_plans user_workout_plans_user_id_workout_plan_id_key; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.user_workout_plans
    ADD CONSTRAINT user_workout_plans_user_id_workout_plan_id_key UNIQUE (user_id, workout_plan_id);


--
-- TOC entry 3523 (class 2606 OID 16527)
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- TOC entry 3526 (class 2606 OID 16523)
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- TOC entry 3529 (class 2606 OID 16525)
-- Name: users users_username_key; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_username_key UNIQUE (username);


--
-- TOC entry 3561 (class 2606 OID 16642)
-- Name: workout_plan_days workout_plan_days_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_plan_days
    ADD CONSTRAINT workout_plan_days_pkey PRIMARY KEY (id);


--
-- TOC entry 3564 (class 2606 OID 16644)
-- Name: workout_plan_days workout_plan_days_workout_plan_id_day_order_key; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_plan_days
    ADD CONSTRAINT workout_plan_days_workout_plan_id_day_order_key UNIQUE (workout_plan_id, day_order);


--
-- TOC entry 3567 (class 2606 OID 16665)
-- Name: workout_plan_exercises workout_plan_exercises_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_plan_exercises
    ADD CONSTRAINT workout_plan_exercises_pkey PRIMARY KEY (id);


--
-- TOC entry 3569 (class 2606 OID 16667)
-- Name: workout_plan_exercises workout_plan_exercises_workout_plan_day_id_exercise_order_key; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_plan_exercises
    ADD CONSTRAINT workout_plan_exercises_workout_plan_day_id_exercise_order_key UNIQUE (workout_plan_day_id, exercise_order);


--
-- TOC entry 3559 (class 2606 OID 16625)
-- Name: workout_plans workout_plans_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_plans
    ADD CONSTRAINT workout_plans_pkey PRIMARY KEY (id);


--
-- TOC entry 3605 (class 2606 OID 25081)
-- Name: workout_session_plan_skips workout_session_plan_skips_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_session_plan_skips
    ADD CONSTRAINT workout_session_plan_skips_pkey PRIMARY KEY (id);


--
-- TOC entry 3608 (class 2606 OID 25083)
-- Name: workout_session_plan_skips workout_session_plan_skips_workout_session_id_workout_plan__key; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_session_plan_skips
    ADD CONSTRAINT workout_session_plan_skips_workout_session_id_workout_plan__key UNIQUE (workout_session_id, workout_plan_exercise_id);


--
-- TOC entry 3578 (class 2606 OID 16713)
-- Name: workout_sessions workout_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_sessions
    ADD CONSTRAINT workout_sessions_pkey PRIMARY KEY (id);


--
-- TOC entry 3589 (class 1259 OID 16837)
-- Name: badges_created_by_user_id_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX badges_created_by_user_id_idx ON public.badges USING btree (created_by_user_id);


--
-- TOC entry 3590 (class 1259 OID 16838)
-- Name: badges_exercise_id_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX badges_exercise_id_idx ON public.badges USING btree (exercise_id);


--
-- TOC entry 3591 (class 1259 OID 16839)
-- Name: badges_muscle_group_id_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX badges_muscle_group_id_idx ON public.badges USING btree (muscle_group_id);


--
-- TOC entry 3540 (class 1259 OID 16820)
-- Name: equipment_name_trgm_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX equipment_name_trgm_idx ON public.equipment USING gin (name public.gin_trgm_ops);


--
-- TOC entry 3553 (class 1259 OID 16824)
-- Name: exercise_muscle_groups_muscle_group_id_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX exercise_muscle_groups_muscle_group_id_idx ON public.exercise_muscle_groups USING btree (muscle_group_id);


--
-- TOC entry 3556 (class 1259 OID 16823)
-- Name: exercise_muscle_groups_primary_exercise_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX exercise_muscle_groups_primary_exercise_idx ON public.exercise_muscle_groups USING btree (exercise_id, involvement, muscle_group_id);


--
-- TOC entry 3543 (class 1259 OID 16817)
-- Name: exercises_active_name_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX exercises_active_name_idx ON public.exercises USING btree (is_active, name);


--
-- TOC entry 3544 (class 1259 OID 16819)
-- Name: exercises_description_trgm_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX exercises_description_trgm_idx ON public.exercises USING gin (description public.gin_trgm_ops);


--
-- TOC entry 3545 (class 1259 OID 16822)
-- Name: exercises_equipment_id_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX exercises_equipment_id_idx ON public.exercises USING btree (equipment_id);


--
-- TOC entry 3548 (class 1259 OID 16818)
-- Name: exercises_name_trgm_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX exercises_name_trgm_idx ON public.exercises USING gin (name public.gin_trgm_ops);


--
-- TOC entry 3609 (class 1259 OID 25109)
-- Name: login_attempts_email_attempted_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX login_attempts_email_attempted_idx ON public.login_attempts USING btree (lower((email)::text), attempted_at DESC);


--
-- TOC entry 3610 (class 1259 OID 25110)
-- Name: login_attempts_ip_attempted_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX login_attempts_ip_attempted_idx ON public.login_attempts USING btree (ip_address, attempted_at DESC);


--
-- TOC entry 3535 (class 1259 OID 16821)
-- Name: muscle_groups_name_trgm_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX muscle_groups_name_trgm_idx ON public.muscle_groups USING gin (name public.gin_trgm_ops);


--
-- TOC entry 3582 (class 1259 OID 16836)
-- Name: performed_sets_exercise_id_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX performed_sets_exercise_id_idx ON public.performed_sets USING btree (exercise_id);


--
-- TOC entry 3585 (class 1259 OID 16834)
-- Name: performed_sets_session_id_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX performed_sets_session_id_idx ON public.performed_sets USING btree (workout_session_id);


--
-- TOC entry 3586 (class 1259 OID 16835)
-- Name: performed_sets_session_performed_at_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX performed_sets_session_performed_at_idx ON public.performed_sets USING btree (workout_session_id, performed_at DESC, id DESC);


--
-- TOC entry 3598 (class 1259 OID 16841)
-- Name: user_badges_badge_id_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX user_badges_badge_id_idx ON public.user_badges USING btree (badge_id);


--
-- TOC entry 3603 (class 1259 OID 16840)
-- Name: user_badges_user_id_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX user_badges_user_id_idx ON public.user_badges USING btree (user_id);


--
-- TOC entry 3570 (class 1259 OID 16829)
-- Name: user_workout_plans_active_user_assigned_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX user_workout_plans_active_user_assigned_idx ON public.user_workout_plans USING btree (user_id, assigned_at DESC) WHERE (is_active = true);


--
-- TOC entry 3573 (class 1259 OID 16828)
-- Name: user_workout_plans_user_id_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX user_workout_plans_user_id_idx ON public.user_workout_plans USING btree (user_id);


--
-- TOC entry 3524 (class 1259 OID 16533)
-- Name: users_email_lower_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE UNIQUE INDEX users_email_lower_idx ON public.users USING btree (lower((email)::text));


--
-- TOC entry 3527 (class 1259 OID 16816)
-- Name: users_role_id_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX users_role_id_idx ON public.users USING btree (role_id);


--
-- TOC entry 3530 (class 1259 OID 16534)
-- Name: users_username_lower_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE UNIQUE INDEX users_username_lower_idx ON public.users USING btree (lower((username)::text));


--
-- TOC entry 3562 (class 1259 OID 16826)
-- Name: workout_plan_days_plan_id_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX workout_plan_days_plan_id_idx ON public.workout_plan_days USING btree (workout_plan_id);


--
-- TOC entry 3565 (class 1259 OID 16827)
-- Name: workout_plan_exercises_day_id_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX workout_plan_exercises_day_id_idx ON public.workout_plan_exercises USING btree (workout_plan_day_id);


--
-- TOC entry 3557 (class 1259 OID 16825)
-- Name: workout_plans_owner_user_id_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX workout_plans_owner_user_id_idx ON public.workout_plans USING btree (owner_user_id);


--
-- TOC entry 3606 (class 1259 OID 25099)
-- Name: workout_session_plan_skips_session_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX workout_session_plan_skips_session_idx ON public.workout_session_plan_skips USING btree (workout_session_id);


--
-- TOC entry 3576 (class 1259 OID 16833)
-- Name: workout_sessions_active_user_started_at_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX workout_sessions_active_user_started_at_idx ON public.workout_sessions USING btree (user_id, started_at DESC) WHERE ((status)::text = 'in_progress'::text);


--
-- TOC entry 3579 (class 1259 OID 16831)
-- Name: workout_sessions_started_at_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX workout_sessions_started_at_idx ON public.workout_sessions USING btree (started_at);


--
-- TOC entry 3580 (class 1259 OID 16830)
-- Name: workout_sessions_user_id_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX workout_sessions_user_id_idx ON public.workout_sessions USING btree (user_id);


--
-- TOC entry 3581 (class 1259 OID 16832)
-- Name: workout_sessions_user_started_at_idx; Type: INDEX; Schema: public; Owner: docker
--

CREATE INDEX workout_sessions_user_started_at_idx ON public.workout_sessions USING btree (user_id, started_at DESC);


--
-- TOC entry 3790 (class 2618 OID 16857)
-- Name: admin_user_overview _RETURN; Type: RULE; Schema: public; Owner: docker
--

CREATE OR REPLACE VIEW public.admin_user_overview AS
 SELECT u.id,
    u.username,
    u.email,
    r.name AS role,
    u.is_active,
    u.blocked_at,
    u.created_at,
    p.firstname,
    p.lastname,
    count(DISTINCT ws.id) FILTER (WHERE ((ws.status)::text = 'finished'::text)) AS finished_workouts
   FROM (((public.users u
     JOIN public.roles r ON ((r.id = u.role_id)))
     LEFT JOIN public.user_profiles p ON ((p.user_id = u.id)))
     LEFT JOIN public.workout_sessions ws ON ((ws.user_id = u.id)))
  GROUP BY u.id, r.name, p.firstname, p.lastname;


--
-- TOC entry 3646 (class 2620 OID 16850)
-- Name: badges badges_touch_updated_at; Type: TRIGGER; Schema: public; Owner: docker
--

CREATE TRIGGER badges_touch_updated_at BEFORE UPDATE ON public.badges FOR EACH ROW EXECUTE FUNCTION public.touch_updated_at();


--
-- TOC entry 3640 (class 2620 OID 16845)
-- Name: exercises exercises_touch_updated_at; Type: TRIGGER; Schema: public; Owner: docker
--

CREATE TRIGGER exercises_touch_updated_at BEFORE UPDATE ON public.exercises FOR EACH ROW EXECUTE FUNCTION public.touch_updated_at();


--
-- TOC entry 3645 (class 2620 OID 16853)
-- Name: performed_sets performed_sets_award_exercise_weight_badges; Type: TRIGGER; Schema: public; Owner: docker
--

CREATE TRIGGER performed_sets_award_exercise_weight_badges AFTER INSERT ON public.performed_sets FOR EACH ROW EXECUTE FUNCTION public.award_exercise_weight_badges();


--
-- TOC entry 3639 (class 2620 OID 16844)
-- Name: user_profiles user_profiles_touch_updated_at; Type: TRIGGER; Schema: public; Owner: docker
--

CREATE TRIGGER user_profiles_touch_updated_at BEFORE UPDATE ON public.user_profiles FOR EACH ROW EXECUTE FUNCTION public.touch_updated_at();


--
-- TOC entry 3643 (class 2620 OID 16848)
-- Name: user_workout_plans user_workout_plans_touch_updated_at; Type: TRIGGER; Schema: public; Owner: docker
--

CREATE TRIGGER user_workout_plans_touch_updated_at BEFORE UPDATE ON public.user_workout_plans FOR EACH ROW EXECUTE FUNCTION public.touch_updated_at();


--
-- TOC entry 3638 (class 2620 OID 16843)
-- Name: users users_touch_updated_at; Type: TRIGGER; Schema: public; Owner: docker
--

CREATE TRIGGER users_touch_updated_at BEFORE UPDATE ON public.users FOR EACH ROW EXECUTE FUNCTION public.touch_updated_at();


--
-- TOC entry 3642 (class 2620 OID 16847)
-- Name: workout_plan_days workout_plan_days_touch_updated_at; Type: TRIGGER; Schema: public; Owner: docker
--

CREATE TRIGGER workout_plan_days_touch_updated_at BEFORE UPDATE ON public.workout_plan_days FOR EACH ROW EXECUTE FUNCTION public.touch_updated_at();


--
-- TOC entry 3641 (class 2620 OID 16846)
-- Name: workout_plans workout_plans_touch_updated_at; Type: TRIGGER; Schema: public; Owner: docker
--

CREATE TRIGGER workout_plans_touch_updated_at BEFORE UPDATE ON public.workout_plans FOR EACH ROW EXECUTE FUNCTION public.touch_updated_at();


--
-- TOC entry 3644 (class 2620 OID 16849)
-- Name: workout_sessions workout_sessions_touch_updated_at; Type: TRIGGER; Schema: public; Owner: docker
--

CREATE TRIGGER workout_sessions_touch_updated_at BEFORE UPDATE ON public.workout_sessions FOR EACH ROW EXECUTE FUNCTION public.touch_updated_at();


--
-- TOC entry 3629 (class 2606 OID 16775)
-- Name: badges badges_created_by_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.badges
    ADD CONSTRAINT badges_created_by_user_id_fkey FOREIGN KEY (created_by_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- TOC entry 3630 (class 2606 OID 16780)
-- Name: badges badges_exercise_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.badges
    ADD CONSTRAINT badges_exercise_id_fkey FOREIGN KEY (exercise_id) REFERENCES public.exercises(id) ON DELETE SET NULL;


--
-- TOC entry 3631 (class 2606 OID 16785)
-- Name: badges badges_muscle_group_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.badges
    ADD CONSTRAINT badges_muscle_group_id_fkey FOREIGN KEY (muscle_group_id) REFERENCES public.muscle_groups(id) ON DELETE SET NULL;


--
-- TOC entry 3616 (class 2606 OID 16600)
-- Name: exercise_muscle_groups exercise_muscle_groups_exercise_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.exercise_muscle_groups
    ADD CONSTRAINT exercise_muscle_groups_exercise_id_fkey FOREIGN KEY (exercise_id) REFERENCES public.exercises(id) ON DELETE CASCADE;


--
-- TOC entry 3617 (class 2606 OID 16605)
-- Name: exercise_muscle_groups exercise_muscle_groups_muscle_group_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.exercise_muscle_groups
    ADD CONSTRAINT exercise_muscle_groups_muscle_group_id_fkey FOREIGN KEY (muscle_group_id) REFERENCES public.muscle_groups(id) ON DELETE RESTRICT;


--
-- TOC entry 3615 (class 2606 OID 16588)
-- Name: exercises exercises_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.exercises
    ADD CONSTRAINT exercises_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;


--
-- TOC entry 3627 (class 2606 OID 16752)
-- Name: performed_sets performed_sets_exercise_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.performed_sets
    ADD CONSTRAINT performed_sets_exercise_id_fkey FOREIGN KEY (exercise_id) REFERENCES public.exercises(id) ON DELETE RESTRICT;


--
-- TOC entry 3628 (class 2606 OID 16747)
-- Name: performed_sets performed_sets_workout_session_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.performed_sets
    ADD CONSTRAINT performed_sets_workout_session_id_fkey FOREIGN KEY (workout_session_id) REFERENCES public.workout_sessions(id) ON DELETE CASCADE;


--
-- TOC entry 3632 (class 2606 OID 16806)
-- Name: user_badges user_badges_badge_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.user_badges
    ADD CONSTRAINT user_badges_badge_id_fkey FOREIGN KEY (badge_id) REFERENCES public.badges(id) ON DELETE CASCADE;


--
-- TOC entry 3633 (class 2606 OID 16811)
-- Name: user_badges user_badges_source_workout_session_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.user_badges
    ADD CONSTRAINT user_badges_source_workout_session_id_fkey FOREIGN KEY (source_workout_session_id) REFERENCES public.workout_sessions(id) ON DELETE SET NULL;


--
-- TOC entry 3634 (class 2606 OID 16801)
-- Name: user_badges user_badges_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.user_badges
    ADD CONSTRAINT user_badges_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 3614 (class 2606 OID 16548)
-- Name: user_profiles user_profiles_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.user_profiles
    ADD CONSTRAINT user_profiles_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 3622 (class 2606 OID 16689)
-- Name: user_workout_plans user_workout_plans_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.user_workout_plans
    ADD CONSTRAINT user_workout_plans_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 3623 (class 2606 OID 16694)
-- Name: user_workout_plans user_workout_plans_workout_plan_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.user_workout_plans
    ADD CONSTRAINT user_workout_plans_workout_plan_id_fkey FOREIGN KEY (workout_plan_id) REFERENCES public.workout_plans(id) ON DELETE CASCADE;


--
-- TOC entry 3613 (class 2606 OID 16528)
-- Name: users users_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE RESTRICT;


--
-- TOC entry 3619 (class 2606 OID 16645)
-- Name: workout_plan_days workout_plan_days_workout_plan_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_plan_days
    ADD CONSTRAINT workout_plan_days_workout_plan_id_fkey FOREIGN KEY (workout_plan_id) REFERENCES public.workout_plans(id) ON DELETE CASCADE;


--
-- TOC entry 3620 (class 2606 OID 16673)
-- Name: workout_plan_exercises workout_plan_exercises_exercise_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_plan_exercises
    ADD CONSTRAINT workout_plan_exercises_exercise_id_fkey FOREIGN KEY (exercise_id) REFERENCES public.exercises(id) ON DELETE RESTRICT;


--
-- TOC entry 3621 (class 2606 OID 16668)
-- Name: workout_plan_exercises workout_plan_exercises_workout_plan_day_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_plan_exercises
    ADD CONSTRAINT workout_plan_exercises_workout_plan_day_id_fkey FOREIGN KEY (workout_plan_day_id) REFERENCES public.workout_plan_days(id) ON DELETE CASCADE;


--
-- TOC entry 3618 (class 2606 OID 16626)
-- Name: workout_plans workout_plans_owner_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_plans
    ADD CONSTRAINT workout_plans_owner_user_id_fkey FOREIGN KEY (owner_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- TOC entry 3635 (class 2606 OID 25094)
-- Name: workout_session_plan_skips workout_session_plan_skips_exercise_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_session_plan_skips
    ADD CONSTRAINT workout_session_plan_skips_exercise_id_fkey FOREIGN KEY (exercise_id) REFERENCES public.exercises(id) ON DELETE RESTRICT;


--
-- TOC entry 3636 (class 2606 OID 25089)
-- Name: workout_session_plan_skips workout_session_plan_skips_workout_plan_exercise_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_session_plan_skips
    ADD CONSTRAINT workout_session_plan_skips_workout_plan_exercise_id_fkey FOREIGN KEY (workout_plan_exercise_id) REFERENCES public.workout_plan_exercises(id) ON DELETE CASCADE;


--
-- TOC entry 3637 (class 2606 OID 25084)
-- Name: workout_session_plan_skips workout_session_plan_skips_workout_session_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_session_plan_skips
    ADD CONSTRAINT workout_session_plan_skips_workout_session_id_fkey FOREIGN KEY (workout_session_id) REFERENCES public.workout_sessions(id) ON DELETE CASCADE;


--
-- TOC entry 3624 (class 2606 OID 16714)
-- Name: workout_sessions workout_sessions_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_sessions
    ADD CONSTRAINT workout_sessions_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 3625 (class 2606 OID 16724)
-- Name: workout_sessions workout_sessions_workout_plan_day_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_sessions
    ADD CONSTRAINT workout_sessions_workout_plan_day_id_fkey FOREIGN KEY (workout_plan_day_id) REFERENCES public.workout_plan_days(id) ON DELETE SET NULL;


--
-- TOC entry 3626 (class 2606 OID 16719)
-- Name: workout_sessions workout_sessions_workout_plan_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: docker
--

ALTER TABLE ONLY public.workout_sessions
    ADD CONSTRAINT workout_sessions_workout_plan_id_fkey FOREIGN KEY (workout_plan_id) REFERENCES public.workout_plans(id) ON DELETE SET NULL;


-- Completed on 2026-06-13 08:43:18 UTC

--
-- PostgreSQL database dump complete
--

\unrestrict EwNhxGYZgHHqQVecJtfg8PUHK232817WRQUgV0kpbOf1g9drHg41SGqLfCsTLUf

