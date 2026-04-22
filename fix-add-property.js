const fs = require('fs');
const filePath = './host/add-property.php';
let content = fs.readFileSync(filePath, 'utf-8');

// Replace the closing script tag and body (handle both CRLF and LF)
const oldPattern1 = '    </script>\r\n</body>\r\n</html>';
const oldPattern2 = '    </script>\n</body>\n</html>';
const newPattern = '    </script>\r\n    <script src="add-property-notifications.js"></script>\r\n</body>\r\n</html>';

if (content.includes(oldPattern1)) {
    content = content.replace(oldPattern1, newPattern);
    fs.writeFileSync(filePath, content, 'utf-8');
    console.log('File updated successfully with CRLF!');
} else if (content.includes(oldPattern2)) {
    content = content.replace(oldPattern2, newPattern.replace(/\r\n/g, '\n'));
    fs.writeFileSync(filePath, content, 'utf-8');
    console.log('File updated successfully with LF!');
} else {
    console.log('Pattern not found in file');
    console.log('Last 300 chars:', content.slice(-300));
}
