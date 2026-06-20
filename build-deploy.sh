#!/bin/bash

# Exit immediately if any command fails
set -e

echo "=== Step 0: Verifying local is up to date with origin/master ==="
git fetch origin --quiet
BEHIND=$(git rev-list --count HEAD..origin/master)
if [ "$BEHIND" -gt 0 ]; then
    echo "Warning: Your local branch is $BEHIND commit(s) behind origin/master."
    read -rp "Run 'git pull' now? [y/N]: " PULL_CHOICE
    case "$PULL_CHOICE" in
        y|Y) git pull origin master || { echo "Error: 'git pull' failed. Aborting."; exit 1; } ;;
        *)   echo "Aborting. Please sync your Codespace before deploying."; exit 1 ;;
    esac
fi
echo " [Done]"

echo "=== Step 1 & 2: Building the release ==="
if [ -f "docs/release/build-release.sh" ]; then

   echo "Select build type:"
   echo "  1) Full install – all customizations"
   echo "  2) Full install – wfdf customization only"
   echo "  3) Update"
   read -rp "Enter choice [1-3]: " BUILD_CHOICE

   case "$BUILD_CHOICE" in
       1) BUILD_ARGS="" ;;
       2) BUILD_ARGS="--cust wfdf" ;;
       3) BUILD_ARGS="--update" ;;
       *) echo "Error: Invalid choice '$BUILD_CHOICE'."; exit 1 ;;
   esac

   bash docs/release/build-release.sh $BUILD_ARGS
   echo " [Done]"
else
   echo "Error: docs/release/build-release.sh not found."
   exit 1
fi

echo "=== Step 3: Unzipping the package ==="
# Quieted the unzip output using the -q flag
unzip -q dist/*.zip -d dist/
echo " [Done]"

echo "=== Step 4: Cleaning up the zip file ==="
rm dist/*.zip
echo " [Done]"

echo "=== Step 5: Renaming the extracted folder ==="
FOUND_FOLDER=$(ls -d dist/ultiorganizer* 2>/dev/null | head -n 1)

if [ -d "$FOUND_FOLDER" ]; then
   if [ -d "dist/production" ]; then
       rm -rf dist/production
   fi
   mv "$FOUND_FOLDER" dist/production
   echo " [Done]"
else
   echo "Error: No folder starting with 'ultiorganizer' was found in dist/"
   exit 1
fi

echo "=== Step 6: Staging files for deployment ==="
rm -rf /tmp/deploy_files/
mkdir -p /tmp/deploy_files/
cp -r dist/production/. /tmp/deploy_files/
echo " [Done]"

echo "=== Step 7: Protecting script and switching branches ==="
mv build-deploy.sh /tmp/build-deploy.sh.bak

# Ensure script and branch are restored on any exit (success or failure)
trap 'mv /tmp/build-deploy.sh.bak build-deploy.sh 2>/dev/null; git checkout -q master 2>/dev/null' EXIT

git checkout -f -q deploy || { echo "Fatal Error: Could not switch to deploy branch. Aborting."; exit 1; }

if [ -d "/tmp/deploy_files/" ] && [ "$(ls -A /tmp/deploy_files/)" ]; then
   git rm -rf --quiet .
   git clean -fdq
   REMAINING=$(git ls-files)
   if [ -n "$REMAINING" ]; then
       echo "Fatal Error: deploy branch not fully cleared. Aborting."
       exit 1
   fi
   echo " [Done]"
else
   echo "Fatal Error: /tmp/deploy_files/ is empty. Aborting git wipe to protect files."
   exit 1
fi

echo "=== Step 8: Copying new files & pushing to Git ==="
cp -r /tmp/deploy_files/. .
git add . > /dev/null
git commit --quiet -m "deploy $(date)"
git push origin deploy --force --quiet
echo " [Done]"

echo "=== Step 9: Returning to master branch ==="
# The trap handles branch switch and script restoration on exit.
# Explicitly removing the trap and doing it manually here ensures
# the success message only prints after a clean completion.
trap - EXIT
git checkout -q master
mv /tmp/build-deploy.sh.bak build-deploy.sh

echo "=== Process Complete: Success ==="