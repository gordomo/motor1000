import copy
import json
import pathlib
import subprocess
import tempfile
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]


def fixture():
    environment = {'APP_ENV': 'production', 'APP_KEY': 'dummy-key', 'DB_DATABASE': 'dummy_db',
                   'DB_USERNAME': 'dummy_user', 'DB_PASSWORD': 'dummy-password'}
    config = {'name': 'motor1000', 'services': {'app': {'environment': environment}}, 'volumes': {}}
    containers = []
    for service in ('app', 'nginx', 'mysql', 'redis', 'horizon', 'scheduler'):
        containers.append({'Id': service + '-id', 'Image': 'sha256:' + service,
                           'State': {'Running': True}, 'Mounts': [],
                           'Config': {'Env': [key + '=' + value for key, value in environment.items()],
                                      'Labels': {'com.docker.compose.service': service,
                                                 'com.docker.compose.project': 'motor1000'}}})
    services = {c['Config']['Labels']['com.docker.compose.service']: c for c in containers}
    services['mysql']['Config']['Env'].append('MYSQL_DATABASE=dummy_db')
    for service, target, name in (
        ('app', '/var/www/storage', 'app_storage'), ('app', '/var/www/bootstrap/cache', 'app_cache'),
        ('mysql', '/var/lib/mysql', 'mysql_data'), ('redis', '/data', 'redis_data'),
    ):
        full_name = 'motor1000_' + name
        config['volumes'][name] = {'name': full_name}
        services[service]['Mounts'].append({'Destination': target, 'Type': 'volume', 'Name': full_name})
    return config, containers


class PreflightTests(unittest.TestCase):
    def run_preflight(self, config, containers):
        with tempfile.TemporaryDirectory() as directory:
            path = pathlib.Path(directory)
            (path / 'config.json').write_text(json.dumps(config))
            (path / 'containers.json').write_text(json.dumps(containers))
            return subprocess.run(['python3', str(ROOT / 'scripts/deploy-preflight.py'),
                                   str(path / 'config.json'), str(path / 'containers.json')],
                                  capture_output=True, text=True)

    def test_accepts_existing_production_volumes(self):
        self.assertEqual(self.run_preflight(*fixture()).returncode, 0)

    def test_rejects_changed_credentials_without_printing_them(self):
        for key in ('APP_KEY', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'):
            config, containers = fixture()
            config['services']['app']['environment'][key] = 'changed-secret'
            result = self.run_preflight(config, containers)
            self.assertNotEqual(result.returncode, 0)
            self.assertNotIn('changed-secret', result.stdout + result.stderr)

    def test_rejects_new_volumes_or_project(self):
        for mutate in (
            lambda config, containers: config['volumes']['mysql_data'].update(name='empty-new-volume'),
            lambda config, containers: config.update(name='different-project'),
            lambda config, containers: containers[0]['Mounts'][0].update(Type='bind'),
        ):
            config, containers = fixture()
            mutate(config, containers)
            self.assertNotEqual(self.run_preflight(config, containers).returncode, 0)

    def test_rejects_stopped_services_or_nonproduction(self):
        config, containers = fixture()
        containers[-1]['State']['Running'] = False
        self.assertNotEqual(self.run_preflight(config, containers).returncode, 0)

    def test_rejects_backup_of_a_different_database(self):
        config, containers = fixture()
        for container in containers:
            if container['Config']['Labels']['com.docker.compose.service'] == 'mysql':
                container['Config']['Env'][-1] = 'MYSQL_DATABASE=another_database'
        self.assertNotEqual(self.run_preflight(config, containers).returncode, 0)
        config, containers = fixture()
        containers[0]['Config']['Env'][0] = 'APP_ENV=local'
        self.assertNotEqual(self.run_preflight(config, containers).returncode, 0)


if __name__ == '__main__':
    unittest.main()
