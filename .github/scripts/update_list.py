import re
import sys

with open('list.txt', 'r', encoding='utf-8') as f:
    list_content = f.read().strip()

with open('index.html', 'r', encoding='utf-8') as f:
    html_content = f.read()

pattern = r'(const\s+RAW_LIST_DATA\s*=\s*`)[^`]*(`;)'
replacement = r'\g<1>\n' + list_content + r'\n\g<2>'

updated_html = re.sub(pattern, replacement, html_content)

with open('index.html', 'w', encoding='utf-8') as f:
    f.write(updated_html)
