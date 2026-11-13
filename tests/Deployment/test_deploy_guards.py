import json
import os
import pathlib
import shutil
import subprocess
import tempfile
import unittest

from test_preflight import ROOT, fixture

FAKE_DOCKER = '''#!/usr/bin/env python3
import json, os, pathlib, subprocess, sys
args = sys.argv[1:]
with open(os.environ['DOCKER_CALLS'], 'a') as log:
    log.write(json.dumps(args) + '\\n')
if args[0] == 'exec' and 'php' in args:
    php_args = args[args.index('php') + 1:]
    result = subprocess.run(['/usr/bin/docker', 'exec', '-i', 'motor1000_app', 'php', *php_args], input=sys.stdin.buffer.read())
    sys.exit(result.returncode)
elif args[0] == 'inspect':
    print(pathlib.Path(os.environ['FAKE_CONTAINERS']).read_text())
elif args[:2] == ['image', 'tag']:
    pass
elif args[0] == 'cp':
    target = pathlib.Path(args[-1])
    if target.is_dir():
        (target / 'index.php').write_text('old public files')
    else:
        target.write_text('old nginx config')
elif 'config' in args:
    print(pathlib.Path(os.environ['FAKE_CONFIG']).read_text())
elif 'ps' in args and '-q' in args:
    print('app-id')
elif 'ps' in args:
    print('app-id\\nnginx-id\\nmysql-id\\nredis-id\\nhorizon-id\\nscheduler-id')
elif 'du' in args:
    print('1\\tdata')
elif 'build' in args:
    sys.exit(17)
else:
    sys.exit('Unexpected Docker mutation: ' + repr(args))
'''


class DeployGuardTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.path = pathlib.Path(self.tmp.name)
        self.repo = self.path / 'repo'
        self.repo.mkdir()
        shutil.copy(ROOT / 'deploy.sh', self.repo)
        shutil.copytree(ROOT / 'scripts', self.repo / 'scripts', ignore=shutil.ignore_patterns('__pycache__'))
        (self.repo / '.env').write_text('APP_ENV=production\n')
        (self.repo / '.gitignore').write_text('.deploy.lock\n')
        (self.repo / 'docker-compose.prod.yml').write_text('services: {}\n')
        for command in (['git', 'init', '-q'], ['git', 'add', '.'],
                        ['git', '-c', 'user.name=Deployment Test', '-c', 'user.email=test@example.test', 'commit', '-qm', 'fixture']):
            subprocess.run(command, cwd=self.repo, check=True)
        self.sha = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=self.repo, text=True).strip()
        config, containers = fixture()
        # Comprueba que los $ literales no se pierdan al generar la recuperación.
        containers[0]['Config']['Env'].append('EXAMPLE_LITERAL=cost$100')
        (self.path / 'config.json').write_text(json.dumps(config))
        (self.path / 'containers.json').write_text(json.dumps(containers))
        binary = self.path / 'bin'
        binary.mkdir()
        (binary / 'docker').write_text(FAKE_DOCKER)
        (binary / 'docker').chmod(0o700)
        self.env = dict(os.environ, PATH=str(binary) + ':' + os.environ['PATH'],
                        FAKE_CONFIG=str(self.path / 'config.json'),
                        FAKE_CONTAINERS=str(self.path / 'containers.json'),
                        DOCKER_CALLS=str(self.path / 'calls.jsonl'),
                        MOTOR1000_BACKUP_DIR=str(self.path / 'backups'))
        self.env.pop('MOTOR1000_BACKUP_MIRROR', None)

    def run_deploy(self, *args):
        return subprocess.run(['bash', 'deploy.sh', *args], cwd=self.repo,
                              env=self.env, text=True, capture_output=True)

    def calls(self):
        path = self.path / 'calls.jsonl'
        return [json.loads(line) for line in path.read_text().splitlines()] if path.exists() else []

    def test_check_is_read_only(self):
        result = self.run_deploy('--check')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertFalse((self.path / 'backups').exists())
        self.assertEqual(len(self.calls()), 5)

    def test_dirty_checkout_stops_before_docker(self):
        (self.repo / 'server-changes.txt').write_text('keep me')
        self.assertNotEqual(self.run_deploy('--check').returncode, 0)
        self.assertEqual(self.calls(), [])
        self.assertEqual((self.repo / 'server-changes.txt').read_text(), 'keep me')

    def test_deploy_requires_exact_revision(self):
        for args in (['--deploy'], ['--deploy', 'invalid-revision']):
            self.assertNotEqual(self.run_deploy(*args).returncode, 0)
            self.assertEqual(self.calls(), [])

    def test_failed_build_never_stops_production_and_preserves_recovery(self):
        # El backup en el mismo VPS está autorizado y no requiere espejo.
        result = self.run_deploy('--deploy', self.sha)
        self.assertEqual(result.returncode, 17, result.stderr)
        self.assertFalse(any('stop' in call or 'down' in call or 'migrate' in call for call in self.calls()))
        backup = next((self.path / 'backups').iterdir())
        recovery = json.loads((backup / 'recovery-images.json').read_text())
        self.assertEqual(recovery['services']['app']['environment']['EXAMPLE_LITERAL'], 'cost$$100')
        subprocess.run(['bash', '-n', str(backup / 'recover.sh')], check=True)
        (backup / 'RELEASED').touch()
        calls_before = self.calls()
        recovery_result = subprocess.run(['bash', str(backup / 'recover.sh')], env=self.env, capture_output=True)
        self.assertNotEqual(recovery_result.returncode, 0)
        self.assertEqual(self.calls(), calls_before)


if __name__ == '__main__':
    unittest.main()
