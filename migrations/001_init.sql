-- Buchlovská spíž — výchozí schéma
-- Vyžaduje MariaDB 10.4+ nebo MySQL 8.0.16+ (vynucované CHECK omezení).
-- Časy (DATETIME) jsou v UTC, data (DATE) jsou kalendářní dny bez časové zóny.

-- ---------------------------------------------------------------------------
-- Uživatelé a pozvánky
-- ---------------------------------------------------------------------------

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  -- Identita v Logto; NULL, dokud pozvaný uživatel nedokončí registraci.
  logto_user_id VARCHAR(64) NULL,
  email VARCHAR(190) NOT NULL,
  role ENUM('producer', 'school', 'admin') NOT NULL,
  status ENUM('invited', 'profile_incomplete', 'active', 'blocked', 'archived') NOT NULL DEFAULT 'invited',
  profile_completed_at DATETIME NULL,
  last_login_at DATETIME NULL,
  -- Pro admina: kdy naposledy prošel přehled nových a upravených záznamů.
  last_review_seen_at DATETIME NULL,
  blocked_at DATETIME NULL,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_logto (logto_user_id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status (role, status),
  -- BINARY: porovnání v ci kolaci by velká písmena nerozlišilo
  CONSTRAINT chk_users_email CHECK (CAST(email AS BINARY) = CAST(LOWER(email) AS BINARY) AND email LIKE '%_@_%'),
  CONSTRAINT chk_users_active_has_identity CHECK (status IN ('invited', 'archived') OR logto_user_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE invitations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  role ENUM('producer', 'school') NOT NULL,
  status ENUM('pending', 'accepted', 'expired', 'revoked') NOT NULL DEFAULT 'pending',
  logto_invitation_id VARCHAR(64) NULL,
  invited_by INT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  accepted_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Nejvýš jedna čekající pozvánka na e-mail (MySQL nemá částečné indexy).
  pending_email VARCHAR(190) AS (IF(status = 'pending', email, NULL)) STORED,
  UNIQUE KEY uq_invitations_pending_email (pending_email),
  KEY idx_invitations_user (user_id),
  KEY idx_invitations_status_expires (status, expires_at),
  CONSTRAINT fk_invitations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_invitations_invited_by FOREIGN KEY (invited_by) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT chk_invitations_expiry CHECK (expires_at > created_at),
  CONSTRAINT chk_invitations_accepted CHECK (status <> 'accepted' OR accepted_at IS NOT NULL),
  CONSTRAINT chk_invitations_revoked CHECK (status <> 'revoked' OR revoked_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- ---------------------------------------------------------------------------
-- Profily organizací (jedna organizace = jeden účet)
-- Změny profilu se zveřejňují okamžitě; kontakty vidí jen přihlášení.
-- ---------------------------------------------------------------------------

CREATE TABLE producer_profiles (
  user_id INT UNSIGNED PRIMARY KEY,
  contact_name VARCHAR(150) NOT NULL,
  producer_name VARCHAR(190) NOT NULL,
  address_line VARCHAR(190) NOT NULL,
  municipality VARCHAR(120) NOT NULL,
  postal_code CHAR(5) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  public_email VARCHAR(190) NOT NULL,
  website VARCHAR(255) NULL,
  company_id CHAR(8) NULL,
  privacy_consent_at DATETIME NOT NULL,
  contact_publication_consent_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_producer_company (company_id),
  CONSTRAINT fk_producer_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT chk_producer_postal CHECK (postal_code REGEXP '^[0-9]{5}$'),
  CONSTRAINT chk_producer_company CHECK (company_id IS NULL OR company_id REGEXP '^[0-9]{8}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE school_profiles (
  user_id INT UNSIGNED PRIMARY KEY,
  school_name VARCHAR(190) NOT NULL,
  contact_name VARCHAR(150) NOT NULL,
  address_line VARCHAR(190) NOT NULL,
  municipality VARCHAR(120) NOT NULL,
  postal_code CHAR(5) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  public_email VARCHAR(190) NOT NULL,
  website VARCHAR(255) NULL,
  -- IČO nebo jiný identifikátor organizace (např. RED IZO)
  organization_id VARCHAR(16) NULL,
  privacy_consent_at DATETIME NOT NULL,
  contact_publication_consent_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_school_organization (organization_id),
  CONSTRAINT fk_school_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT chk_school_postal CHECK (postal_code REGEXP '^[0-9]{5}$'),
  CONSTRAINT chk_school_organization CHECK (organization_id IS NULL OR organization_id REGEXP '^[0-9]{6,16}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- ---------------------------------------------------------------------------
-- Číselníky spravované administrátorem
-- ---------------------------------------------------------------------------

CREATE TABLE categories (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(110) NOT NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_categories_name (name),
  UNIQUE KEY uq_categories_slug (slug),
  CONSTRAINT chk_categories_slug CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE units (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(8) NOT NULL,
  label VARCHAR(30) NOT NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_units_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- ---------------------------------------------------------------------------
-- Nabídky zemědělců a poptávky škol
-- Stavy podle docs/ROZHODNUTI.md: bez předběžného schvalování.
--   active      zveřejněno
--   ended       ručně ukončeno autorem
--   expired     automaticky po datu konce
--   withdrawn   staženo adminem (s důvodem)
--   resubmitted opraveno po stažení, čeká na uvolnění adminem
-- deleted_at = měkké smazání autorem (záznam nevidí nikdo, zůstává pro audit).
-- content_updated_at = poslední změna obsahu autorem (přehled pro admina).
-- ---------------------------------------------------------------------------

CREATE TABLE offers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  producer_user_id INT UNSIGNED NOT NULL,
  category_id SMALLINT UNSIGNED NOT NULL,
  product_name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  unit_price DECIMAL(10, 2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'CZK',
  price_unit_id SMALLINT UNSIGNED NOT NULL,
  available_quantity DECIMAL(12, 3) NOT NULL,
  quantity_unit_id SMALLINT UNSIGNED NOT NULL,
  availability_type ENUM('immediate', 'from_date') NOT NULL,
  available_from DATE NULL,
  available_until DATE NULL,
  delivery_terms VARCHAR(1000) NOT NULL,
  status ENUM('active', 'ended', 'expired', 'withdrawn', 'resubmitted') NOT NULL DEFAULT 'active',
  withdrawn_by INT UNSIGNED NULL,
  withdrawn_at DATETIME NULL,
  withdrawal_reason VARCHAR(1000) NULL,
  ended_at DATETIME NULL,
  deleted_at DATETIME NULL,
  content_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_offers_market (status, deleted_at, created_at),
  KEY idx_offers_category (category_id, status),
  KEY idx_offers_producer (producer_user_id, deleted_at),
  KEY idx_offers_until (status, available_until),
  KEY idx_offers_review (content_updated_at),
  CONSTRAINT fk_offers_producer FOREIGN KEY (producer_user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_offers_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT,
  CONSTRAINT fk_offers_price_unit FOREIGN KEY (price_unit_id) REFERENCES units (id) ON DELETE RESTRICT,
  CONSTRAINT fk_offers_quantity_unit FOREIGN KEY (quantity_unit_id) REFERENCES units (id) ON DELETE RESTRICT,
  CONSTRAINT fk_offers_withdrawn_by FOREIGN KEY (withdrawn_by) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT chk_offers_price CHECK (unit_price > 0),
  CONSTRAINT chk_offers_currency CHECK (currency = 'CZK'),
  CONSTRAINT chk_offers_quantity CHECK (available_quantity > 0),
  CONSTRAINT chk_offers_name CHECK (CHAR_LENGTH(TRIM(product_name)) > 0),
  CONSTRAINT chk_offers_delivery CHECK (CHAR_LENGTH(TRIM(delivery_terms)) > 0),
  CONSTRAINT chk_offers_availability CHECK (
    (availability_type = 'immediate' AND available_from IS NULL)
    OR (availability_type = 'from_date' AND available_from IS NOT NULL)
  ),
  CONSTRAINT chk_offers_dates CHECK (available_until IS NULL OR available_from IS NULL OR available_until >= available_from),
  CONSTRAINT chk_offers_withdrawal CHECK (
    status NOT IN ('withdrawn', 'resubmitted')
    OR (withdrawal_reason IS NOT NULL AND CHAR_LENGTH(TRIM(withdrawal_reason)) > 0 AND withdrawn_at IS NOT NULL AND withdrawn_by IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE demands (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_user_id INT UNSIGNED NOT NULL,
  category_id SMALLINT UNSIGNED NOT NULL,
  product_name VARCHAR(150) NOT NULL,
  requested_quantity DECIMAL(12, 3) NOT NULL,
  quantity_unit_id SMALLINT UNSIGNED NOT NULL,
  needed_from DATE NOT NULL,
  needed_until DATE NOT NULL,
  note TEXT NULL,
  status ENUM('active', 'ended', 'expired', 'withdrawn', 'resubmitted') NOT NULL DEFAULT 'active',
  withdrawn_by INT UNSIGNED NULL,
  withdrawn_at DATETIME NULL,
  withdrawal_reason VARCHAR(1000) NULL,
  ended_at DATETIME NULL,
  deleted_at DATETIME NULL,
  content_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_demands_market (status, deleted_at, created_at),
  KEY idx_demands_category (category_id, status),
  KEY idx_demands_school (school_user_id, deleted_at),
  KEY idx_demands_until (status, needed_until),
  KEY idx_demands_review (content_updated_at),
  CONSTRAINT fk_demands_school FOREIGN KEY (school_user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_demands_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT,
  CONSTRAINT fk_demands_quantity_unit FOREIGN KEY (quantity_unit_id) REFERENCES units (id) ON DELETE RESTRICT,
  CONSTRAINT fk_demands_withdrawn_by FOREIGN KEY (withdrawn_by) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT chk_demands_quantity CHECK (requested_quantity > 0),
  CONSTRAINT chk_demands_name CHECK (CHAR_LENGTH(TRIM(product_name)) > 0),
  CONSTRAINT chk_demands_dates CHECK (needed_until >= needed_from),
  CONSTRAINT chk_demands_withdrawal CHECK (
    status NOT IN ('withdrawn', 'resubmitted')
    OR (withdrawal_reason IS NOT NULL AND CHAR_LENGTH(TRIM(withdrawal_reason)) > 0 AND withdrawn_at IS NOT NULL AND withdrawn_by IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- ---------------------------------------------------------------------------
-- Auditní protokol (akce adminů, stažení a uvolnění, úpravy záznamů autory)
-- ---------------------------------------------------------------------------

CREATE TABLE audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  -- NULL = systémová akce (např. automatická expirace)
  actor_user_id INT UNSIGNED NULL,
  action VARCHAR(64) NOT NULL,
  target_type VARCHAR(32) NOT NULL,
  target_id INT UNSIGNED NULL,
  -- JSON: např. změněná pole nebo důvod stažení; nikdy hesla ani tokeny
  details TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_target (target_type, target_id),
  KEY idx_audit_actor (actor_user_id, created_at),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT chk_audit_details CHECK (details IS NULL OR JSON_VALID(details))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- ---------------------------------------------------------------------------
-- Výchozí číselníky (SPECIFIKACE §14)
-- ---------------------------------------------------------------------------

INSERT INTO categories (name, slug, sort_order) VALUES
  ('Zelenina', 'zelenina', 10),
  ('Ovoce', 'ovoce', 20),
  ('Obiloviny a mouka', 'obiloviny-a-mouka', 30),
  ('Maso a uzeniny', 'maso-a-uzeniny', 40),
  ('Mléko a mléčné výrobky', 'mleko-a-mlecne-vyrobky', 50),
  ('Vejce', 'vejce', 60),
  ('Pečivo', 'pecivo', 70),
  ('Med a včelí produkty', 'med-a-vceli-produkty', 80),
  ('Nápoje', 'napoje', 90),
  ('Ostatní', 'ostatni', 100);

INSERT INTO units (code, label, sort_order) VALUES
  ('kg', 'kilogram', 10),
  ('t', 'tuna', 20),
  ('g', 'gram', 30),
  ('l', 'litr', 40),
  ('ml', 'mililitr', 50),
  ('ks', 'kus', 60);
