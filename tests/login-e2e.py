#!/usr/bin/env python3
"""HTTP end-to-end checks against an explicitly selected, isolated local site.

Uses real cookies, CSRF tokens, native login POSTs and redirects. No browser or
production data is used. Credentials come only from the process environment.
"""
import http.cookiejar
import json
import os
import re
import secrets
import socket
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser


class Markup(HTMLParser):
    def __init__(self, html):
        super().__init__()
        self.fields, self.apps = {}, []
        self.hidden, self.checked, self.roles = {}, {}, {}
        self.role_value, self.role_label = None, []
        self.feed(html)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag in ('input', 'button') and attrs.get('name'):
            name, value = attrs['name'], attrs.get('value', '')
            self.fields[name] = value
            if tag == 'input' and attrs.get('type') == 'hidden':
                self.hidden[name] = value
            if tag == 'input' and 'checked' in attrs:
                self.checked.setdefault(name, []).append(value)
            if tag == 'input' and name == 'roles[]':
                self.role_value, self.role_label = value, []
        if tag == 'a' and 'ws-app-launch' in attrs.get('class', '').split():
            self.apps.append(attrs['href'])

    def handle_data(self, data):
        if self.role_value is not None:
            self.role_label.append(data)

    def handle_endtag(self, tag):
        if tag == 'label' and self.role_value is not None:
            self.roles[''.join(self.role_label).strip()] = self.role_value
            self.role_value = None


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


@unittest.skipUnless(os.environ.get('WORKSPACE_E2E_URL'), 'Set WORKSPACE_E2E_URL to an isolated local site')
class LoginE2E(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.base = os.environ['WORKSPACE_E2E_URL'].rstrip('/')
        host = urllib.parse.urlparse(cls.base).hostname
        if not host or not all(a[4][0] in ('127.0.0.1', '::1') for a in socket.getaddrinfo(host, None)):
            raise RuntimeError('Only loopback development sites are allowed')
        cls.admin = os.environ.get('WORKSPACE_E2E_ADMIN_PATH', '/workspace-admin/').rstrip('/') + '/'

    def setUp(self):
        self.cookies = http.cookiejar.CookieJar()
        self.client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))

    def request(self, path, data=None, redirect=True):
        client = self.client if redirect else urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.cookies), NoRedirect())
        request = urllib.request.Request(urllib.parse.urljoin(self.base + '/', path),
            data=urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None,
            headers={'User-Agent': 'Workspace-local-login-e2e/1.0'})
        try:
            response = client.open(request, timeout=15)
        except urllib.error.HTTPError as response_error:
            response = response_error
        with response:
            return response.status, response.headers, response.read().decode(), response.url

    def assert_authenticated(self, html):
        self.assertTrue('id="ProcessLoginForm"' not in html, 'Session returned to login')
        self.assertTrue('ws-account-name' in html, 'Authenticated shell is missing')

    def login(self, user, password):
        path = self.admin + 'login/'
        for attempt in range(2):
            fields = Markup(self.request(path)[2]).fields
            self.assertTrue(any(name.startswith('TOKEN') for name in fields), 'Native CSRF token missing')
            fields.update(login_name=user, login_pass=password, login_submit=fields.get('login_submit') or 'Login')
            response = self.request(path, fields)[2]
            delay = re.search(r'Please wait at least (\d+) seconds before attempting another login', response)
            if not delay or attempt == 1 or int(delay.group(1)) >= 30:
                return response
            # Keep native login throttling active on isolated fixtures.
            time.sleep(int(delay.group(1)) + 1)

    def password_form(self):
        path = self.admin + 'workspace/password/'
        status, _, html, _ = self.request(path)
        self.assertEqual(status, 200)
        self.assert_authenticated(html)
        fields = Markup(html).fields
        self.assertTrue({'pass', '_pass', '_old_pass', 'save_password'} <= fields.keys())
        self.assertTrue(any(name.startswith('TOKEN') for name in fields), 'Password CSRF token missing')
        return fields

    def submit_password(self, current, new, confirm):
        fields = self.password_form()
        fields.update(_old_pass=current, **{'pass': new, '_pass': confirm})
        return self.request(self.admin + 'workspace/password/', fields)[2]

    def test_operator_launcher_access_and_self_service_password(self):
        user = os.environ.get('WORKSPACE_E2E_OPERATOR')
        password = os.environ.get('WORKSPACE_E2E_OPERATOR_PASSWORD')
        if not user or not password:
            self.skipTest('Supply disposable operator fixture credentials through environment')
        self.assert_authenticated(self.login(user, password))
        home = self.request(self.admin + 'workspace/')[2]
        self.assertIn(self.admin + 'workspace/password/', home)
        self.assertNotIn(self.admin + 'access/users/', home)
        apps = Markup(home).apps
        self.assertTrue(apps, 'Operator fixture needs at least one launchable app')
        for app in apps:
            with self.subTest(app=app):
                status, _, body, _ = self.request(app)
                self.assertEqual(status, 200)
                self.assert_authenticated(body)
        for path in ('access/users/', 'workspace/appearance/'):
            with self.subTest(denied=path):
                status, _, body, _ = self.request(self.admin + path)
                self.assertIn(status, (200, 403))
                self.assertTrue(
                    re.search(r'not available in Workspace|Administrator access is required', body)
                    or '<title>Login &middot; Workspace</title>' in body
                    or 'id="ProcessLoginForm"' in body,
                    'Operator reached a privileged process',
                )

        new_password = 'Pw!z9-' + secrets.token_hex(14)
        rejected = self.submit_password('Wrong-' + secrets.token_hex(8), new_password, new_password)
        self.assertIn('The old password you entered is not correct', rejected)
        self.assertTrue(new_password not in rejected, 'Rejected password is present in the response')
        rejected = self.submit_password(password, new_password, new_password + '-mismatch')
        self.assertIn('Passwords do not match', rejected)
        self.assertTrue(new_password not in rejected, 'Rejected password is present in the response')

        fields = self.password_form()
        fields = {name: value for name, value in fields.items() if not name.startswith('TOKEN')}
        fields.update(_old_pass=password, **{'pass': new_password, '_pass': new_password})
        status, _, rejected, _ = self.request(self.admin + 'workspace/password/', fields)
        self.assertLess(status, 500, 'Missing CSRF must not crash the handler')
        self.assertNotIn('Your password was changed.', rejected)
        self.assertTrue(new_password not in rejected, 'Rejected password is present in the response')

        changed = self.submit_password(password, new_password, new_password)
        try:
            self.assertTrue('Your password was changed.' in changed, 'Password update was not confirmed')
            self.request(self.admin + 'login/logout/')
            self.assert_authenticated(self.login(user, new_password))
            rejected = self.submit_password(password, password, password)
            self.assertIn('The old password you entered is not correct', rejected)
        finally:
            if 'id="ProcessLoginForm"' in self.request(self.admin + 'workspace/')[2]:
                self.login(user, new_password)
            if 'Your password was changed.' in changed:
                restored = self.submit_password(new_password, password, password)
                self.assertIn('Your password was changed.', restored, 'Fixture password was not restored')
        self.request(self.admin + 'login/logout/')
        self.assertIn('id="ProcessLoginForm"', self.request(self.admin + 'workspace/')[2])

    def test_superuser_can_open_native_user_creation_from_workspace(self):
        user = os.environ.get('WORKSPACE_E2E_USER')
        password = os.environ.get('WORKSPACE_E2E_PASSWORD')
        if not user or not password:
            self.skipTest('Supply disposable superuser fixture credentials through environment')
        self.assert_authenticated(self.login(user, password))
        html = self.request(self.admin + 'workspace/')[2]
        self.assertIn(self.admin + 'access/users/', html)
        status, _, html, _ = self.request(self.admin + 'access/users/')
        self.assertEqual(status, 200)
        self.assertIn(self.admin + 'access/users/add/', html)
        add_path = self.admin + 'access/users/add/'
        status, _, html, _ = self.request(add_path)
        self.assertEqual(status, 200)
        self.assert_authenticated(html)
        self.assertIn('Add New', html)
        form = Markup(html)
        self.assertIn('_pw_page_name', form.fields)
        role = os.environ.get('WORKSPACE_E2E_EMPLOYEE_ROLE')
        if not role:
            return

        employee_name = 'workspace-e2e-' + secrets.token_hex(8)
        initial_password = 'Pw!z9-' + secrets.token_hex(14)
        new_password = 'Pw!z9-' + secrets.token_hex(14)
        status, _, html, url = self.request(add_path, {
            **form.hidden, '_pw_page_name': employee_name, 'submit_save': form.fields['submit_save'],
        })
        self.assertEqual(status, 200)
        form = Markup(html)
        user_id = form.hidden.get('id', '')
        self.assertTrue(user_id.isdecimal(), 'Native Add User did not open a saved user record')
        edit_path = self.admin + 'access/users/edit/?id=' + user_id
        self.assertEqual(urllib.parse.urlparse(url).path, self.admin + 'access/users/edit/')
        try:
            self.assertIn(role, form.roles, 'Configured fixture role is missing from the native form')
            self.assertEqual(form.checked.get('admin_theme'), ['AdminThemeWorkspace'])
            payload = {
                **form.hidden,
                '_pw_page_name': employee_name,
                'pass': initial_password,
                '_pass': initial_password,
                'email': employee_name + '@example.invalid',
                'roles[]': [*form.checked.get('roles[]', []), form.roles[role]],
                'admin_theme': 'AdminThemeWorkspace',
                'submit_publish': form.fields['submit_publish'],
            }
            if form.checked.get('language'):
                payload['language'] = form.checked['language'][0]
            status, _, html, _ = self.request(edit_path, payload)
            self.assertEqual(status, 200)
            self.assertTrue('Published Page' in html, 'Native editor did not publish the employee')

            employee = LoginE2E()
            employee.setUp()
            employee.assert_authenticated(employee.login(employee_name, initial_password))
            home = employee.request(self.admin + 'workspace/')[2]
            employee.assert_authenticated(home)
            self.assertTrue(Markup(home).apps, 'Employee role has no available apps')
            changed = employee.submit_password(initial_password, new_password, new_password)
            self.assertTrue('Your password was changed.' in changed, 'Employee password did not change')
            employee.request(self.admin + 'login/logout/')
            employee.assert_authenticated(employee.login(employee_name, new_password))
            employee.request(self.admin + 'login/logout/')
        finally:
            cleanup = Markup(self.request(edit_path)[2])
            self.assertEqual(cleanup.fields.get('delete_page'), user_id, 'Native deletion confirmation is missing')
            status, _, _, url = self.request(edit_path, {
                **cleanup.hidden, 'delete_page': user_id, 'submit_delete': cleanup.fields['submit_delete'],
            })
            self.assertEqual(status, 200)
            self.assertEqual(urllib.parse.urlparse(url).path, self.admin + 'access/users/')
            self.assertNotIn('delete_page', Markup(self.request(edit_path)[2]).fields)

    def test_demo_redirect_is_temporary_and_not_cached(self):
        status, headers, _, _ = self.request('/demo/', redirect=False)
        self.assertEqual(status, 302, 'A cached permanent redirect bypasses subsequent logins')
        self.assertIn('no-store', headers.get('Cache-Control', ''))
        self.assertEqual(headers.get('Location'), self.admin + 'workspace/')

    def test_demo_apps_refresh_logout_and_reentry(self):
        _, _, html, _ = self.request('/demo/?e2e=login')
        self.assert_authenticated(html)
        apps = Markup(html).apps
        self.assertTrue(apps, 'No installed apps are available to the prepared operator')
        for path in apps:
            with self.subTest(app=path):
                status, _, html, _ = self.request(path)
                self.assertEqual(status, 200)
                self.assert_authenticated(html)
                self.assert_authenticated(self.request(path)[2])
        self.request(self.admin + 'login/logout/')
        self.assertIn('id="ProcessLoginForm"', self.request(self.admin + 'workspace/')[2])
        self.assert_authenticated(self.request('/demo/?e2e=reentry')[2])

    def test_native_admin_login_persists_and_logout_revokes_it(self):
        user = os.environ.get('WORKSPACE_E2E_USER')
        password = os.environ.get('WORKSPACE_E2E_PASSWORD')
        if not user or not password:
            self.skipTest('Supply prepared fixture credentials through environment')
        self.assert_authenticated(self.login(user, password))
        html = self.request(self.admin + 'workspace/')[2]
        self.assert_authenticated(html)
        match = re.search(r'var ProcessWire = \{ config: (.*?) \};', html, re.S)
        self.assertIsNotNone(match, 'Native JavaScript configuration is missing')
        configuration = json.loads(match.group(1))
        self.assertEqual(configuration.get('urls', {}).get('admin'), self.admin)
        # PW 3.0.275+ exports the icon configuration consumed by its core JS.
        if os.environ.get('WORKSPACE_E2E_CORE_ICONS') == '1':
            self.assertIn('version', configuration.get('adminIcons', {}))
        self.assertEqual(len(re.findall(r'var ProcessWire =', html)), 1)
        self.request(self.admin + 'login/logout/')
        self.assertIn('id="ProcessLoginForm"', self.request(self.admin + 'workspace/')[2])


if __name__ == '__main__':
    unittest.main(verbosity=2)
