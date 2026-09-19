# IEEE BECITHCON 2026 Codeword & QR Generator

A small local web application for creating conference records.

## What it does

- You can select a conference or event name, or enter a custom name
- Name and Role are always included in the QR code
- Conference/Event and Session can be included or excluded with checkboxes
- Role, Conference, and Event options can be added or removed from the form; changes are saved in the browser
- You enter:
  - Role
  - Name
  - Session
- The system generates a random unique 16-character codeword
- Each Conference/Event + Role combination is saved to its own Excel file in `registrations/`
- A black-and-white QR code is generated
- QR file name uses the codeword
- You can download the QR image
- You can download the Excel file
- The last 10 entries are shown on the page
- Exact duplicate Person + Role + Session entries are blocked

## QR data format

The QR code contains:

Conference/Event: the selected conference or event name
Role: ...
Name: ...
Session: ...
Codeword: ...

## Windows setup

### 1. Install Python

Install Python 3.11 or newer from python.org.

During installation, enable:

`Add Python to PATH`

### 2. Open this folder in VS Code

Open VS Code, then:

File -> Open Folder -> select `becithcon_qr_system`

### 3. Open Terminal in VS Code

Terminal -> New Terminal

### 4. Create a virtual environment

```bash
python -m venv .venv
```

Activate it:

```bash
.venv\Scripts\activate
```

### 5. Install packages

```bash
pip install -r requirements.txt
```

### 6. Run the system

```bash
python app.py
```

Open:

`http://127.0.0.1:5000`

## Files

- `app.py` - main program
- `registrations/` - one Excel file per Conference/Event + Role combination
- `templates/index.html` - web form and result page
- `static/css/style.css` - design
- `static/qr/` - generated QR images

## Important

This version is made mainly for one computer / one operator.

If many people must use the system at the same time, move the records to SQLite or PostgreSQL and use Excel only for export.

Before public deployment, replace the Flask secret key in `app.py`.
