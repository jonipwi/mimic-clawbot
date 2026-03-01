# AI Buddy

This directory contains the AI Buddy service used by the Clawbot project.

## Overview

AI Buddy interacts with various messaging platforms and provides AI-driven responses. It maintains state in the `state/` directory and logs activity to `logs/log.txt`.

## Setup

1. Copy `.env.example` to `.env` and populate environment variables.
2. Ensure clawbot-api.exe and configured if building any components.
3. Run the service with the appropriate command (e.g. `.\clawbot-api.exe` or via existing scripts).

## CLI

The project provides a command-line interface. Use:

.\clawbotctl.exe
usage: clawbotctl <approve|send> [flags]
  approve --channel=telegram [--chat-id ID | --code CODE] [--api URL]
  send    --channel=whatsapp --to=NUMBER --message=TEXT [--api URL]     

## Directories

- `logs/` - contains runtime logs.
- `state/` - persistent state such as memory or session data.

## Distribution

You can download a pre-built distribution archive here:

https://dev.benwinindonesia.com/ai-buddy.7z

## Contributing

See the top-level repository README for contribution guidelines.
