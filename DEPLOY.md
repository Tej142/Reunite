
# 🚀 Reunite — Cloud Deployment Guide

> This guide explains how to permanently deploy Reunite so your team, mentors, and anyone with the link can access and test it anytime — even when your machine is off.

---

## 📋 What You Are Deploying

Reunite has two parts. Both need to be deployed separately:

| Part | Technology | Where to deploy |
|---|---|---|
| **AI Backend** | Python Flask Server | **Render.com** (free) |
| **Frontend** | PHP + HTML/CSS/JS | **InfinityFree** (free) |

Complete them in order: **Backend first, then Frontend.**

---

## 🛠️ What You Need Before Starting

- A **GitHub account** → https://github.com
- A **Render.com account** → https://render.com
- An **InfinityFree account** → https://infinityfree.com
- Your **Gemini API key** → https://aistudio.google.com/app/apikey
- Your **Mistral API key** → https://console.mistral.ai/api-keys

All of the above are **free**.

---

## ─────────────────────────────────────────────
## PART 1 — Push Your Code to GitHub
## ─────────────────────────────────────────────

> This is required so Render can pull your code automatically.

### Step 1 — Create a GitHub Repository

1. Go to → https://github.com/new
2. Name it `reunite`
3. Set visibility to **Private**
4. Leave everything else as default and click **Create repository**

---

### Step 2 — Push Your Project to GitHub

Open **Command Prompt** or **PowerShell**, navigate to the project folder, and run these commands one by one:

```bash
cd C:\xampp\htdocs\Reunite
```

```bash
git init
```

```bash
git add .
```

```bash
git commit -m "Initial deployment commit"
```

```bash
git branch -M main
```

```bash
git remote add origin https://github.com/YOUR_USERNAME/reunite.git
```

```bash
git push -u origin main
```

> Replace `YOUR_USERNAME` with your actual GitHub username.

✅ **Done.** Your code is now on GitHub.

---

## ─────────────────────────────────────────────
## PART 2 — Deploy the Flask AI Backend on Render.com
## ─────────────────────────────────────────────

### Step 1 — Sign In to Render

1. Go to → https://render.com
2. Click **Get Started for Free**
3. Sign up / log in using your **GitHub account**

---

### Step 2 — Create a New Web Service

1. On the dashboard, click **New +** in the top right
2. Select **Web Service** from the dropdown
3. Under "Connect a repository", select your **`reunite`** repo
4. Click **Connect**

---

### Step 3 — Fill in the Configuration

You will see a settings form. Fill it in exactly like this:

| Field | What to enter |
|---|---|
| **Name** | `reunite-ai-backend` |
| **Region** | Pick the one closest to you |
| **Branch** | `main` |
| **Runtime** | `Python 3` |
| **Build Command** | `pip install -r requirements.txt` |
| **Start Command** | `python flask_server.py` |
| **Instance Type** | `Free` |

---

### Step 4 — Add Your API Keys

Scroll down to the **Environment Variables** section.

Click **Add Environment Variable** and add these two entries:

| Key | Value |
|---|---|
| `GEMINI_API_KEY` | Paste your Gemini API key here |
| `MISTRAL_API_KEY` | Paste your Mistral API key here |

> 🔐 This is the secure way to use API keys on the cloud. They are stored privately on Render and are never visible in your code or on GitHub.

---

### Step 5 — Click Deploy

Click **Create Web Service** at the bottom of the page.

Render will now:
1. Pull your code from GitHub
2. Install Python dependencies automatically
3. Start the Flask server

This takes about **2 to 5 minutes**. You can watch the log output on screen.

When it is ready, you will see a green dot and a live URL at the top:

```
●  Live   https://reunite-ai-backend.onrender.com
```

📌 **Copy this URL and save it.** You will need it in the next part.

---

### ⚠️ Important: Free Tier Behaviour

On Render's free plan, the server **goes to sleep** after 15 minutes of no activity. The first request after it sleeps will take **30–60 seconds** to respond while it wakes up. This is completely normal. After it wakes up, all requests are fast.

---

## ─────────────────────────────────────────────
## PART 3 — Connect & Deploy the PHP Frontend on InfinityFree
## ─────────────────────────────────────────────

### Step 1 — Update the Backend URL in Your Code

Before uploading the frontend, you need to point it to your live Flask backend.

Open this file in a code editor:

```
Frontend/components/nav.php
```

Find this block near the top of the file:

```html
<script>
  window.FLASK_BACKEND_URL = window.FLASK_BACKEND_URL || 'http://127.0.0.1:5000';
</script>
```

Change it to your Render URL from Part 2:

```html
<script>
  window.FLASK_BACKEND_URL = 'https://reunite-ai-backend.onrender.com';
</script>
```

Save the file.

---

### Step 2 — Create an InfinityFree Account

1. Go to → https://www.infinityfree.com
2. Click **Sign Up** — it is completely free, no credit card needed
3. Check your email and verify your account

---

### Step 3 — Create a Hosting Account

1. Log in to InfinityFree and click **Create Account**
2. Choose a free subdomain name — for example: `reunite-app`
   - Your app will be live at: `https://reunite-app.rf.gd`
3. Click **Create**
4. Wait about 1 minute for the account to activate

---

### Step 4 — Upload Your Frontend Files

You have two methods to upload. Pick whichever is easier for you:

---

**Method A — File Manager (simpler, good for small uploads)**

1. In your InfinityFree dashboard, click **File Manager**
2. Navigate into the `htdocs` folder
3. Click **Upload** and select all the files and folders inside your local `Frontend/` directory
4. Wait for the upload to finish

---

**Method B — FTP with FileZilla (recommended for full uploads)**

1. In your InfinityFree dashboard, go to **FTP Details** and copy:
   - FTP Hostname
   - FTP Username
   - FTP Password

2. Download and install **FileZilla** (free) → https://filezilla-project.org

3. Open FileZilla. At the top toolbar, enter:
   - **Host:** your FTP Hostname
   - **Username:** your FTP Username
   - **Password:** your FTP Password
   - **Port:** `21`
   - Click **Quickconnect**

4. On the **right panel** (server side), open the `htdocs` folder

5. On the **left panel** (your computer), navigate to:
   ```
   C:\xampp\htdocs\Reunite\Frontend\
   ```

6. Select all files and folders inside `Frontend/` and drag them into `htdocs/` on the right panel

7. Wait for the upload to complete — you will see a progress log at the bottom of FileZilla

---

### Step 5 — Visit Your Live App

Open your browser and go to:

```
https://reunite-app.rf.gd/index.php
```

Your Reunite app is now live on the internet. 🎉

---

## ✅ Deployment Complete!

Share this link with your team and mentors:

```
https://reunite-app.rf.gd/index.php
```

Anyone with this link can access and test the full app from any device, anytime — no setup needed on their end.

---

## 🐛 If Something Goes Wrong

| Problem | How to fix it |
|---|---|
| Render deployment fails | Click **Logs** on the Render dashboard and read the exact error message |
| AI does not respond on the live site | Check that your API keys are correctly added in Render → Environment Variables |
| InfinityFree shows a blank or error page | Make sure files are placed directly inside `htdocs/`, not inside a subfolder within it |
| First AI request is very slow | Normal — Render free tier wakes up after sleep. Wait 30–60 seconds and try again |
| Frontend connects to wrong backend | Re-check `Frontend/components/nav.php` — the URL must exactly match your Render URL |

---

*Reunite — Cloud Deployment Guide · September 2026*

