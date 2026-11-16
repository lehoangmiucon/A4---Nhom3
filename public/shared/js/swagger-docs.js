window.addEventListener('load', () => {
    const container = document.getElementById('swagger-ui');
    const status = document.getElementById('swagger-auth-status');
    const api = container.dataset.apiUrl.replace(/\/$/, '');

    window.ui = SwaggerUIBundle({
        spec: JSON.parse(document.getElementById('swagger-spec').textContent),
        dom_id: '#swagger-ui',
        deepLinking: true,
        filter: true,
        docExpansion: 'none',
        tryItOutEnabled: true,
        requestSnippetsEnabled: true,
        displayRequestDuration: true,
        persistAuthorization: false,
        validatorUrl: null,
        defaultModelsExpandDepth: -1,
        presets: [SwaggerUIBundle.presets.apis],
        requestInterceptor(request) {
            request.credentials = 'omit';
            request.headers = request.headers || {};
            request.headers['Accept'] = 'application/json';
            request.headers['X-Helpdesk-Client'] = 'swagger-ui';
            return request;
        },
        responseInterceptor(response) {
            if (response.status < 200 || response.status >= 300) return response;
            const url = new URL(response.url, window.location.href);
            const endpoint = url.origin + url.pathname.replace(/\/$/, '');
            if (endpoint === api + '/auth/login') {
                status.textContent = 'Đã đăng nhập. Dán access_token vào Authorize.';
                status.hidden = false;
            } else if (endpoint === api + '/auth/logout') {
                window.ui.authActions.logout(['bearerAuth']);
                status.textContent = 'Đã đăng xuất.';
                status.hidden = false;
            }
            return response;
        }
    });
});
