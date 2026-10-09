CREATE TABLE IF NOT EXISTS "#__pungacontactform_limits" (
    "ip_hash" CHAR(64) NOT NULL,
    "hour_bucket" BIGINT NOT NULL,
    "attempts" INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY ("ip_hash", "hour_bucket")
);
CREATE INDEX IF NOT EXISTS "idx_pungacontactform_bucket" ON "#__pungacontactform_limits" ("hour_bucket");
