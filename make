# --- VARIABLES ---
# Change these values to match your specific branch and project name
BRANCH = main
COMMIT_MSG = "Auto-update via Makefile"
REMOTE_URL = https://github.com

# --- COMMANDS ---

# Default action when you just type 'make'
all: status

# 1. Check current repository status
status:
	git status

# 2. Download and merge the latest updates from GitHub
pull:
	git pull origin $(BRANCH)

# 3. Stage all modified files, commit them, and upload to GitHub
push:
	git add .
	git commit -m $(COMMIT_MSG)
	git push origin $(BRANCH)

# 4. Same as push, but lets you type a custom commit message
# Example usage: make commit MSG="added new login feature"
commit:
	git add .
	git commit -m "$(MSG)"
	git push origin $(BRANCH)

# 5. Link a brand new local folder to your remote repository for the first time
init:
	git init
	git remote add origin $(REMOTE_URL)
	git branch -M $(BRANCH)
	@echo "Repository initialized and connected to origin!"
