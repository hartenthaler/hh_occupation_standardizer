-- HISCO catalog schema used by hh_occupation_standardizer.
-- This SQL file documents the module-specific structure. The tables are
-- created by src/Infrastructure/Persistence/Schema/OccupationSchema.php.
--
-- Source: Van Leeuwen, IISH Data Collection, Files from HISCO database,
-- version V2, 2016, doi 10622/JA9B8O, https://hdl.handle.net/10622/JA9B8O

CREATE TABLE occupation_standardizer_hisco_major_groups (
    major_id       TINYINT UNSIGNED NOT NULL,
    label_en       VARCHAR(255) NOT NULL,
    label_de       VARCHAR(255) NULL,
    description_en TEXT NOT NULL,
    description_de TEXT NULL,
    updated_at     TIMESTAMP NULL,
    PRIMARY KEY (major_id)
);

CREATE TABLE occupation_standardizer_hisco_minor_groups (
    minor_id       TINYINT UNSIGNED NOT NULL,
    major_id       TINYINT UNSIGNED NOT NULL,
    label_en       VARCHAR(255) NOT NULL,
    label_de       VARCHAR(255) NULL,
    description_en TEXT NOT NULL,
    description_de TEXT NULL,
    updated_at     TIMESTAMP NULL,
    PRIMARY KEY (minor_id),
    KEY idx_occ_std_hisco_minor_major (major_id)
);

CREATE TABLE occupation_standardizer_hisco_unit_groups (
    unit_id        SMALLINT UNSIGNED NOT NULL,
    minor_id       TINYINT UNSIGNED NOT NULL,
    label_en       VARCHAR(255) NOT NULL,
    label_de       VARCHAR(255) NULL,
    description_en TEXT NOT NULL,
    description_de TEXT NULL,
    updated_at     TIMESTAMP NULL,
    PRIMARY KEY (unit_id),
    KEY idx_occ_std_hisco_unit_minor (minor_id)
);

CREATE TABLE occupation_standardizer_hisco_occupations (
    hisco_id       MEDIUMINT UNSIGNED NOT NULL,
    unit_id        SMALLINT UNSIGNED NOT NULL,
    micro_suffix   TINYINT UNSIGNED NOT NULL,
    hisco_pretty   VARCHAR(10) NOT NULL,
    label_en       VARCHAR(255) NOT NULL,
    description_en TEXT NOT NULL,
    updated_at     TIMESTAMP NULL,
    PRIMARY KEY (hisco_id),
    KEY idx_occ_std_hisco_pretty (hisco_pretty),
    KEY idx_occ_std_hisco_occupation_unit (unit_id)
);

CREATE TABLE occupation_standardizer_hisco_classifications (
    hisco_id   MEDIUMINT UNSIGNED NOT NULL,
    hiscam_u1  DECIMAL(5, 2) NULL,
    hiscam_nl  DECIMAL(5, 2) NULL,
    occ1950    SMALLINT UNSIGNED NULL,
    hisclass   TINYINT UNSIGNED NULL,
    hisclass_5 TINYINT UNSIGNED NULL,
    PRIMARY KEY (hisco_id)
);
