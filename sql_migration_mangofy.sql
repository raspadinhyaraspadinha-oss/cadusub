-- =============================================================================
-- Migration SQL for Subway Pay - Mangofy + UTMify + Facebook CAPI Integration
-- Execute this in your MySQL database to add the required columns.
-- =============================================================================

-- Add facebook_capi_token column to app table (for Facebook Conversions API)
ALTER TABLE `app` ADD COLUMN IF NOT EXISTS `facebook_capi_token` VARCHAR(500) DEFAULT '' AFTER `facebook_ads_tag`;

-- Note: The gateway table already has client_id and client_secret columns.
-- Now they store Mangofy credentials:
--   client_id    = Mangofy Authorization Token
--   client_secret = Mangofy Store Code
-- No schema change needed for the gateway table.

-- Note: confirmar_deposito.externalreference now stores the Mangofy payment_code
-- (instead of SuitPay idTransaction). No schema change needed.
