-- The suite runs against its own database so it cannot destroy development data.
-- Created here rather than by the test bootstrap: CREATE DATABASE cannot run inside
-- a transaction, and the bootstrap wraps everything in one.
CREATE DATABASE shortwave_testing OWNER shortwave;
