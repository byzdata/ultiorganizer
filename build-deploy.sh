#!/bin/bash

# Exit immediately if any command fails
set -e

echo "=== Step 1 & 2: Building the release ==="
if [ -f "docs/release/build-release.sh" ]; then
    bash docs/release/build-release.sh --cust wfdf
else
    echo "Error: docs/release/build-release.sh not found."
    exit 1
fi

echo "=== Step 3: Unzipping the package ==="
unzip dist/*.zip -d dist/

echo "=== Step 4: Cleaning up the zip file ==="
rm dist/*.zip

echo "=== Step 5: Renaming the extracted folder ==="
FOUND_FOLDER=$(ls -d dist/ultiorganizer* 2>/dev/null | head -n 1)

if [ -d "$FOUND_FOLDER" ]; then
    if [ -d "dist/production" ]; then
        echo "Removing old dist/production folder..."
        rm -rf dist/production
    fi
    echo "Renaming $FOUND_FOLDER to dist/production..."
    mv "$FOUND_FOLDER" dist/production
else
    echo "Error: No folder starting with 'ultiorganizer' was found in dist/"
    exit 1
fi

echo "=== Step 6: Staging files for deployment ==="
# Ensure the temporary deployment folder is completely fresh
rm -rf /tmp/deploy_files/
mkdir -p /tmp/deploy_files/

# Copy your production build to the temporary staging area
cp -r dist/production/ /tmp/deploy_files/

echo "=== Step 7: Switching branches and clearing old files ==="
git checkout deploy

# CRITICAL SAFETY: Ensure temp files exist before erasing the current repository contents
if [ -d "/tmp/deploy_files/" ] && [ "$(ls -A /tmp/deploy_files/)" ]; then
    git rm -rf .
else
    echo "Fatal Error: /tmp/deploy_files/ is empty. Aborting git wipe to protect files."
    git checkout master
    exit 1
fi

echo "=== Step 8: Copying new files & pushing to Git ==="
# Copy contents from staging back into the root repository
cp -r /tmp/deploy_files/. .

# Stage, commit, and force-push the changes to the deploy branch
git add .
git commit -m "deploy $(date)"
git push origin deploy --force

echo "=== Step 9: Returning to master branch ==="
git checkout master

echo "=== Process Complete: Success ==="
