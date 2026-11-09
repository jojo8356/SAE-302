"""Execute a native PHP test and annotate its failure on CI, including fatal errors."""
import subprocess
import sys

if len(sys.argv) != 2:
    raise SystemExit('Usage: python scripts/ci_php_diagnostic.py tests/php/<test>.php')

result = subprocess.run(['php', '-d', 'display_errors=1', sys.argv[1]],
                        stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
output = result.stdout.decode('utf-8', errors='replace')
print(output, end='')
if result.returncode:
    lines = output.splitlines()
    relevant = [line for line in lines if any(marker in line.lower() for marker in
                ('❌', 'fatal error', 'uncaught', 'exception', 'warning', 'error:'))]
    if not relevant:
        relevant = lines[-8:] or [f'PHP terminated with exit code {result.returncode} without output']
    for line in relevant[-12:]:
        safe = line.replace('%', '%25').replace('\r', '%0D').replace('\n', '%0A')
        print(f'::error title=Diagnostic PHP::{safe}')
raise SystemExit(result.returncode)
