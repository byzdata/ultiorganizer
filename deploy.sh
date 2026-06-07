#!/bin/bash

echo "🚀 Starting deploy process..."

# 1. Make sure we're on master
git checkout master
if [ $? -ne 0 ]; then
  echo "❌ Failed to switch to master branch"
  exit 1
fi

# 2. Check dist/production exists
if [ ! -d "dist/production" ]; then
  echo "❌ dist/production folder not found. Build first!"
  exit 1
fi

# 3. Copy to temp
echo "📦 Copying dist/production to temp..."
rm -rf /tmp/deploy_files/
cp -r dist/production/ /tmp/deploy_files/

# 4. Switch to deploy branch
echo "🔀 Switching to deploy branch..."
git checkout deploy
if [ $? -ne 0 ]; then
  echo "❌ Failed to switch to deploy branch"
  git checkout master
  exit 1
fi

# 5. Wipe and replace
echo "🗑️  Clearing deploy branch..."
git rm -rf .
cp -r /tmp/deploy_files/. .

# 6. Commit and push
echo "⬆️  Pushing to GitHub..."
git add .
git commit -m "deploy $(date '+%Y-%m-%d %H:%M:%S')"
git push origin deploy --force

# 7. Go back to master
echo "🔙 Switching back to master..."
git checkout master

echo "✅ Deploy complete! Go to Hostinger and click Deploy."
