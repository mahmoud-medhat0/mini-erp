const fs = require('fs');
const path = require('path');

function checkDuplicates(filePath) {
  const content = fs.readFileSync(filePath, 'utf8');
  const stack = [];
  const lines = content.split('\n');
  
  // We can parse line by line and track key paths
  const keyCounts = {};
  
  // Parse with custom JSON parser tracking line numbers and duplicates
  let currentPath = [];
  
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const match = line.match(/"([^"]+)":\s*[\{\[\"0-9tfn]/);
    if (match) {
      const key = match[1];
      // simplified path tracking based on indentation
      const indent = line.search(/\S/);
      const level = Math.floor(indent / 2);
      currentPath = currentPath.slice(0, level);
      currentPath[level] = key;
      
      const fullPath = currentPath.slice(0, level + 1).join('.');
      if (!keyCounts[fullPath]) {
        keyCounts[fullPath] = [];
      }
      keyCounts[fullPath].push(i + 1);
    }
  }
  
  console.log(`=== Duplicate Keys in ${path.basename(filePath)} ===`);
  for (const [keyPath, lineNums] of Object.entries(keyCounts)) {
    if (lineNums.length > 1) {
      console.log(`Duplicate key "${keyPath}" found at lines: ${lineNums.join(', ')}`);
    }
  }
}

const enPath = path.join(__dirname, '../laravel/resources/js/locales/en.json');
const arPath = path.join(__dirname, '../laravel/resources/js/locales/ar.json');

checkDuplicates(enPath);
checkDuplicates(arPath);
