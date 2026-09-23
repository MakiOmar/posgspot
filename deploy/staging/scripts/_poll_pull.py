import os
import paramiko

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(
    "82.29.178.160",
    username="root",
    password=os.environ["VPS_SSH_PASS"],
    timeout=60,
    allow_agent=False,
    look_for_keys=False,
)
stdin, stdout, stderr = c.exec_command(
    "ps aux | grep -E 'pull-from|mysqldump|rsync|sshpass' | grep -v grep; "
    "echo ---LOG---; tail -50 /tmp/spot-pull.log; "
    "echo ---DUMPS---; ls -lh /opt/spot-staging/data/dumps 2>/dev/null || true"
)
print(stdout.read().decode("utf-8", "replace"))
c.close()
