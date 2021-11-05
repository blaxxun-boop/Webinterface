CREATE TABLE IF NOT EXISTS user (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, password TEXT, group_id INTEGER, last_login INTEGER, steam_id TEXT, forcePasswordChange BOOLEAN);
CREATE TABLE IF NOT EXISTS permission (group_id INTEGER, permission TEXT, FOREIGN KEY(group_id) REFERENCES permissionGroup(group_id));
CREATE INDEX IF NOT EXISTS group_id ON permission (group_id);
CREATE TABLE IF NOT EXISTS permissionGroup (group_id INTEGER PRIMARY KEY AUTOINCREMENT, groupname TEXT UNIQUE);
CREATE TABLE IF NOT EXISTS keys (key TEXT PRIMARY KEY, value TEXT);

INSERT OR IGNORE INTO permissionGroup VALUES (0, 'Logged out User'), (1, 'Admin');
INSERT OR IGNORE INTO permission VALUES (1, 'Admin');