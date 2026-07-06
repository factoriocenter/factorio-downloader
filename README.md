# Factorio Downloader 🚀

**Factorio Downloader** is a fan-made web application that enables you to download various versions of Factorio—including the full game, demo, headless server version, and the Space Age expansion—with ease and efficiency. The site dynamically filters available downloads based on your selected version and detected operating system, ensuring that only authorized users (those who own the base game—and, when required, the Space Age DLC) can access the downloads.

> **Important Security Notice:**  
> To help prevent piracy, you **must** configure your environment by filling out the **.env** file with your Factorio credentials. Downloads will only proceed if your account owns the game (and the Space Age DLC for expansion downloads).

---

## Table of Contents 📑

- [Factorio Downloader 🚀](#factorio-downloader-)
  - [Table of Contents 📑](#table-of-contents-)
  - [Features ✨](#features-)
  - [Technologies Used 🛠️](#technologies-used-️)
  - [Installation 🚀](#installation-)
  - [Usage 🎮](#usage-)
  - [Obtaining Your Token via cURL 🔑](#obtaining-your-token-via-curl-)
    - [On Linux and macOS](#on-linux-and-macos)
    - [On Windows](#on-windows)
  - [Project Structure 📁](#project-structure-)
  - [Environment Configuration (.env) ⚙️](#environment-configuration-env-️)
  - [Contributing 🤝](#contributing-)
  - [License 📄](#license-)
  - [Acknowledgments 🙏](#acknowledgments-)
- [Screenshot](#screenshot)

---

## Features ✨

- **Version Filtering:**  
  Displays a complete, ordered list of Factorio versions (from **Latest Version** down to **0.6.4**) as defined in the configuration. When you select a version, the site dynamically shows only the relevant download sections.

- **Multiple Download Categories:**  
  - **Full Game:** Download the complete version of Factorio.
  - **Demo:** Access the free, publicly available demo version.
  - **Server (Headless):** Download the headless server version (available on Linux only).
  - **Space Age:** Download the Space Age expansion (available from version 2.0.7 onward).

- **Dynamic OS Detection:**  
  Automatically detects your operating system (Windows, macOS, or Linux) and highlights the corresponding download button for a seamless experience.

- **Authentication & Ownership Verification:**  
  For Full Game and Space Age downloads, the application uses credentials defined in the **.env** file (loaded via Dotenv) to authenticate with Factorio’s API. Downloads proceed only if:
  - The login and password are correct.
  - Your account owns the base game (and the Space Age DLC, if applicable).

- **Direct Download Redirection:**  
  Instead of proxying downloads through your server, the system retrieves the official download URL from Factorio’s servers and redirects your browser directly, ensuring fast and reliable downloads.

---

## Technologies Used 🛠️

- **PHP:** Server-side scripting and dynamic page rendering.
- **cURL:** For fetching data from Factorio’s API and following redirects.
- **jq:** A lightweight command-line JSON processor used to parse API responses.
- **CSS & HTML:** For a clean, Factorio-inspired user interface.
- **dotenv (vlucas/phpdotenv):** For environment variable management (credentials are loaded from a **.env** file).
- **ChatGPT o3-mini:** Approximately 90% of the project’s code and structure was developed with assistance from ChatGPT o3-mini.

---

## Installation 🚀

1. **Clone the Repository:**

   ```bash
   git clone https://github.com/louanfontenele/Factorio-Downloader.git
   ```

2. **Configure the Environment:**

   - **Create and fill the `.env` file:**  
     Copy the provided `.env.example` (if available) to `.env` and fill in your Factorio credentials. This step is essential to ensure that downloads (for full game and expansion) work only when proper credentials are provided—helping to prevent piracy.

     Example **.env** file:

     ```dotenv
     FACTORIO_LOGIN=your_login
     FACTORIO_PASSWORD=your_password
     # Optionally, set a fallback token obtained via cURL:
     FACTORIO_TOKEN_FALLBACK=your_fallback_token
     ```

   - The `.env` file is loaded automatically. If Composer's `vlucas/phpdotenv` is
     installed (`vendor/`), it is used; otherwise a small built-in parser reads
     `.env`. **`composer install` is optional** — the app runs on a plain host
     (e.g. cPanel via git) without it.

3. **Deploy to a PHP-Enabled Web Server:**

   - Upload all project files to your server (e.g., using cPanel/git, Dokploy/Nixpacks, Docker, Apache, or Nginx with PHP-FPM).
   - Ensure that the required PHP extensions (e.g., cURL) are enabled.
   - Make sure `.env` (your credentials) and `versions.json` are present on the server.
     `.env` is git-ignored, so create it directly on the host; `versions.json` is
     committed and ships with the repository.
   - If using Docker, build the container with the provided Dockerfile (which creates a persistent volume for certificates and runs `composer install`).

4. **Access the Application:**

   - Open your browser and navigate to your project URL (e.g., `http://yourdomain.com/index.php`).

---

## Usage 🎮

- **Select a Version:**  
  The homepage displays an ordered list of available Factorio versions. Click on a version to filter the download sections.

- **Download Content:**  
  Depending on the selected version:
  - Download options for the Full Game, Demo, Server (Headless), and Space Age will be displayed.
  - For Full Game and Space Age downloads, authentication is enforced—your account must own the game (and the DLC, if applicable).
  - The Server (Headless) version is available only for Linux.

- **Direct Redirection:**  
  When you click a download button, the application retrieves the official download URL from Factorio’s servers and redirects your browser directly.

---

## Obtaining Your Token 🔑

Even though our project no longer reads a local `player-data.json` file, you still need a valid token for authentication (for Full Game and Expansion downloads).

### Recommended: the cross-platform helper script

The easiest way, on **Windows, Linux and macOS**, is the bundled PHP helper:

```bash
php scripts/get-token.php
```

It asks for your login and password (the password is hidden), requests the token
from Factorio's API, prints it, and can write it straight to your `.env` as
`FACTORIO_TOKEN_FALLBACK`. It needs only PHP with cURL (already required by this
project) — no `jq` or platform-specific tweaks. You can also pre-set the
`FACTORIO_LOGIN` / `FACTORIO_PASSWORD` environment variables to run it non-interactively.

### Alternative: raw cURL

Per the official [Web authentication API](https://wiki.factorio.com/Web_authentication_API),
credentials must be sent in the **POST body** (`application/x-www-form-urlencoded`),
**not** as URL query-string parameters. The single command below works the same in a
Linux/macOS shell, Windows CMD, and PowerShell (it uses the real `curl`; `--data-urlencode`
handles encoding, so no `jq` is needed). Replace `YOUR_LOGIN` and `YOUR_PASSWORD`:

```sh
curl -X POST "https://auth.factorio.com/api-login" --data-urlencode "username=YOUR_LOGIN" --data-urlencode "password=YOUR_PASSWORD" --data-urlencode "require_game_ownership=true" --data-urlencode "api_version=2"
```

On success you get a JSON object with the token:

```json
{ "token": "8me7gpab3nrqt5fahf7b363qa65uh7", "username": "your_login" }
```

(Omit `api_version=2` and the response is instead an array: `["8me7gpab3nrqt5fahf7b363qa65uh7"]`.)

Copy the token and add it to your `.env` file:

```dotenv
FACTORIO_TOKEN_FALLBACK=8me7gpab3nrqt5fahf7b363qa65uh7
```

> **Note (PowerShell):** in older Windows PowerShell, `curl` is an alias for
> `Invoke-WebRequest` and does **not** accept `--data-urlencode` (you may see
> `built-in manual was disabled at build-time` or similar). Use `curl.exe` explicitly,
> run it from CMD, or just use the `php scripts/get-token.php` helper above.

> **Note:**  
>
> - Your account must own the base game (and the Space Age DLC, if required) for the token to be issued.  
> - Some accounts require an e-mail authentication code on login; resend it via the `email_authentication_code` field (the helper script prompts for it automatically).
> - Tokens may expire over time, so if downloads start failing, request a new one.

---

## Project Structure 📁

```
Factorio-Downloader/
├─ config.php              # Loads credentials (.env) and the version data from versions.json
├─ versions.json           # Auto-generated version data per distro (updated daily, do not edit by hand)
├─ .env                    # Environment file with sensitive credentials (not committed)
├─ strings.php             # Centralized English strings for the UI
├─ theme.php               # Handles version selection, filtering, and label assignment (Stable/Experimental)
├─ download.php            # PHP script for authenticating and redirecting to the download URL
├─ index.php               # Main entry point of the website
├─ scripts/
│   ├─ update-versions.php  # Refreshes versions.json from Factorio's official APIs
│   └─ get-token.php        # Cross-platform helper to fetch your Factorio auth token
├─ .github/workflows/
│   └─ update-versions.yml  # Daily GitHub Action that runs the updater and commits changes
└─ site/
    ├─ downloadFactorio.php  # Full Game download section
    ├─ downloadDemo.php      # Demo download section
    ├─ downloadServer.php    # Headless Server download section (Linux only)
    ├─ downloadSpaceAge.php  # Space Age expansion download section
    ├─ gameVersions.php      # Displays the list of available versions
    ├─ header.php            # Contains the header, meta tags, and CSS/JS links
    ├─ menu.php              # Site navigation and header logo
    └─ footer.php            # Footer content and scripts
```

---

## Version Data & Automation 🔄

The available Factorio versions are **no longer maintained by hand**. They live in
`versions.json`, one block per distribution (base game, demo, headless server and the
Space Age expansion), each split into `stable` and `experimental` lists:

```json
{
  "factorio": { "stable": ["..."], "experimental": ["2.1.9"] },
  "demo":     { "stable": ["..."], "experimental": ["..."] },
  "server":   { "stable": ["..."], "experimental": ["..."] },
  "spaceage": { "stable": ["..."], "experimental": ["..."] },
  "all":      ["2.1.9", "...", "0.6.4"]
}
```

`config.php` reads this file and rebuilds the same variables the site already used, so
the rest of the code is untouched.

A GitHub Action (`.github/workflows/update-versions.yml`) runs
`scripts/update-versions.php` once a day (08:00 BRT / 11:00 UTC, plus manual
`workflow_dispatch`). It reads two official Factorio APIs:

- <https://factorio.com/api/latest-releases> — current stable/experimental head of each distro.
- <https://updater.factorio.com/get-available-versions> — full headless history (safety net).

and applies deltas only:

- a **new experimental** release is appended to that distro's `experimental` list;
- when an experimental build is **promoted to stable**, it is moved into `stable` (this is
  exactly what happened with `2.0.77`).

If anything changed, the workflow commits the updated `versions.json` straight to `main`.
Existing versions are never dropped, so nothing you already had is lost. You can also run
it locally:

```bash
php scripts/update-versions.php
```

---

## Environment Configuration (.env) ⚙️

To ensure that downloads are allowed only for authorized users, you **must** create a `.env` file in the project root with your Factorio credentials. For example:

```dotenv
FACTORIO_LOGIN=your_login
FACTORIO_PASSWORD=your_password
# Optional: Provide a fallback token obtained via cURL if direct authentication fails:
FACTORIO_TOKEN_FALLBACK=your_fallback_token
```

These credentials (and the fallback token) are used to authenticate with Factorio’s API. **Downloads will only proceed if your account owns the game (and the Space Age DLC, when applicable).**

---

## Contributing 🤝

Contributions are welcome! If you’d like to enhance the project, please open an issue or submit a pull request on the [GitHub repository](https://github.com/louanfontenele/Factorio-Downloader/).

---

## License 📄

This project is provided as a fan project and is not officially affiliated with Wube Software. Use it at your own risk. Please ensure you comply with all relevant licensing and distribution laws.

---

## Acknowledgments 🙏

- **ChatGPT o3-mini:** Approximately 90% of the project’s code and structure was developed with assistance from ChatGPT o3-mini.
- **Factorio Community:** Thanks to the vibrant Factorio community and existing projects for inspiration.
- **Tools:** Special thanks to the developers of PHP, cURL, jq, and vlucas/phpdotenv for providing robust tools that made this project possible.

---

Enjoy Factorio and happy downloading! 🎉

---

# Screenshot

![FacDL Screenshot](factoriodownloader.png "Factorio Downloader Screenshot")
