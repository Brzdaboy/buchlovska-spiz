-- ID jednorázového tokenu v Logto (samotný token se NIKDY neukládá),
-- aby šlo pozvánku při zrušení nebo opětovném odeslání zneplatnit i v Logto.
ALTER TABLE invitations
  ADD COLUMN logto_one_time_token_id VARCHAR(64) NULL AFTER logto_invitation_id;
