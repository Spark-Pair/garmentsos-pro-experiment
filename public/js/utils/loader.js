function initGlobalLoader() {
    function bindPageHandlers() {
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function () {
                const href = this.getAttribute('href');
                const target = this.getAttribute('target');

                if (
                    href &&
                    !href.startsWith('#') &&
                    !href.startsWith('javascript:') &&
                    !target
                ) {
                    showLoader();
                }
            });
        });

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function () {
                showLoader();
            });
        });
    }

    window.addEventListener('beforeunload', function () {
        showLoader();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindPageHandlers);
    } else {
        bindPageHandlers();
    }

    window.addEventListener('load', function () {
        hideLoader();
    });

    if (typeof axios !== 'undefined') {
        axios.interceptors.request.use(config => {
            showLoader();
            return config;
        }, error => {
            hideLoader();
            return Promise.reject(error);
        });

        axios.interceptors.response.use(response => {
            hideLoader();
            return response;
        }, error => {
            hideLoader();
            return Promise.reject(error);
        });
    }

    if (typeof $ !== 'undefined') {
        $(document).ajaxStart(function () {
            showLoader();
        }).ajaxStop(function () {
            if (!doHide) {
                hideLoader();
            }
            doHide = false;
        });
    }
}
