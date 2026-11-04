#!/usr/bin/env python3
"""HTTP end-to-end checks against an explicitly selected, isolated local site.

Uses real cookies, CSRF tokens, native login POSTs and redirects. No browser or
production data is used. Credentials come only from the process environment.
"""
import http.cookiejar
import json
import os
import re
import socket
import unittest
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser


class Markup(HTMLParser):
    def __init__(self, html):
        super().__init__()
        self.fields, self.apps = {}, []
        self.feed(html)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag in ('input', 'button') and attrs.get('name'):
            self.fields[attrs['name']] = attrs.get('value', '')
        if tag == 'a' and 'ws-app-launch' in attrs.get('class', '').split():
            self.apps.append(attrs['href'])


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
            data=urllib.parse.urlencode(data).encode() if data is not None else None,
            headers={'User-Agent': 'Workspace-local-login-e2e/1.0'})
        try:
            response = client.open(request, timeout=15)
        except urllib.error.HTTPError as response_error:
            response = response_error
        with response:
            return response.status, response.headers, response.read().decode(), response.url

    def assert_authenticated(self, html):
        self.assertNotIn('id="ProcessLoginForm"', html, 'Session returned to login')
        self.assertIn('ws-account-name', html, 'Authenticated shell is missing')

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
        _, _, html, _ = self.request(self.admin + 'login/')
        fields = Markup(html).fields
        self.assertTrue(any(name.startswith('TOKEN') for name in fields), 'Native CSRF token missing')
        fields.update(login_name=user, login_pass=password, login_submit=fields.get('login_submit') or 'Login')
        self.assert_authenticated(self.request(self.admin + 'login/', fields)[2])
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
