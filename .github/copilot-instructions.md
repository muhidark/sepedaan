<!-- Copilot / AI Agent instructions for the Sepedaan app -->
# Sepedaan — Copilot Instructions

Purpose: short, actionable guidance so an AI coding agent can be immediately productive in this PHP + frontend XAMPP project.

**Big Picture**
- **Stack**: Plain PHP (mysqli) backend, minimal SPA-like frontend in `index.php` using CDN libraries (Tailwind, `html5-qrcode`) and browser Geolocation. Admin UI is server-rendered in `admin.php`.
- **Data flow**: Frontend calls `api.php?action=...` (POST JSON) → `api.php` reads `config.php` for MySQL connection → reads/writes `participants`, `checkpoints`, `checkpoint_logs` tables.

**Key Files**
- `index.php`: participant-facing app. Uses `Html5Qrcode` for QR scanning and `navigator.geolocation` for GPS. Important checks: `window.isSecureContext` and `window.location.protocol` (camera may require https/secure context).
- `api.php`: single entrypoint for JSON API. Switches by `$_GET['action']`. Two implemented actions: `register` and `scan`.
- `config.php`: mysqli connection parameters (assumes XAMPP defaults: `root` / empty password). Agents should update DB access here when deploying.
- `admin.php`: server-rendered dashboard; demonstrates how logs are joined and displayed.

**API patterns & examples**
- Endpoint routing: `api.php?action=register` and `api.php?action=scan` (GET param routes behavior).
- Request style: POST JSON body (read via `file_get_contents('php://input')` + `json_decode`). Example: `fetch('api.php?action=register', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({name,whatsapp,email})})`.
- Response shape: JSON with `success` (bool), `message` (string), and optional `data` object.
- `scan` logic: expects `participant_id`, `checkpoint_code`, `user_latitude`, `user_longitude`. Backend computes Haversine distance and enforces `max_radius_meters` (default CP max in DB is 25m in current checks).

**Database & conventions (discoverable)**
- Tables used: `participants`, `checkpoints`, `checkpoint_logs` (see queries in `api.php` and `admin.php`).
- Checkpoint codes are stored uppercase. API uppercases incoming codes: `strtoupper($cp_code)`.
- Logs: `checkpoint_logs` stores `participant_id`, `checkpoint_code`, `user_latitude`, `user_longitude`, `distance_meters`, and `scanned_at` (used by `admin.php`).

**Development / debug workflow**
- Local: project expects XAMPP (Apache + MySQL). Start Apache & MySQL, then open `http://localhost/sepedaan/index.php` or `admin.php`.
- Camera note: Browsers block camera access unless served over HTTPS or specific localhost conditions. `index.php` explicitly logs `isSecureContext` and `protocol` — for local camera testing use `https`, `localhost`, or a tunnel (ngrok) to satisfy secure context.

**Coding patterns & constraints**
- DB access: code uses `mysqli` with inline SQL (string interpolation). When adding new queries, follow existing style for consistency (but be cautious of SQL injection if modifying inputs).
- API routing is centralized in `api.php` via `action` GET parameter. Add new features by adding another `if ($action === 'your_action') { ... }` block and follow the same JSON input/output conventions.
- Frontend persistence: participant session is stored in `localStorage` under key `cycling_user`.

**Where to change common things**
- Add DB config / credentials: `config.php`.
- Add new API actions: `api.php` (follow pattern: read JSON, validate, query DB, echo json).
- Add UI changes: `index.php` for participant flows, `admin.php` for management UI.

**Quick examples**
- To validate a new API action, mimic existing style:
  - Read JSON: `$data = json_decode(file_get_contents('php://input'), true);`
  - Return JSON: `echo json_encode(["success"=>true,"message"=>"OK","data"=>$payload]);`
- To add a new checkpoint in DB: insert into `checkpoints(code, name, latitude, longitude, max_radius_meters)` and ensure `code` is uppercase.

If anything here is unclear or you'd like additional sections (security notes, migration steps, or tests), tell me which parts to expand and I'll iterate.
