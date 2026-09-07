// This file is part of Moodle - http://moodle.org/.

define([], function() {
    var loaded = {};
    var active = {};

    function unique(values) {
        var seen = {};
        return values.filter(function(value) {
            value = String(value || '').trim();
            if (!value || seen[value]) {
                return false;
            }
            seen[value] = true;
            return true;
        });
    }

    function sdkUrls(src, configuredVersion) {
        var versions = [];
        var configured = String(configuredVersion || '').trim();
        if (configured && configured !== 'auto') {
            versions.push(configured.replace(/[^0-9.]/g, ''));
        }
        // Newer DocSpace Cloud tenants generally expose 2.2.0. Keep 2.0.0
        // as the compatibility fallback for older tenants.
        versions.push('2.2.0');
        versions.push('2.0.0');
        return unique(versions).map(function(version) {
            return src + '/static/scripts/sdk/' + version + '/api.js';
        });
    }

    function loadOne(url) {
        if (loaded[url]) {
            return loaded[url];
        }
        loaded[url] = new Promise(function(resolve, reject) {
            if (window.DocSpace && window.DocSpace.SDK) {
                resolve(url);
                return;
            }
            var script = document.createElement('script');
            script.src = url;
            script.async = true;
            script.dataset.worksheetgraderDocspace = '1';
            script.onload = function() {
                if (window.DocSpace && window.DocSpace.SDK) {
                    resolve(url);
                } else {
                    reject(new Error('SDK script loaded but DocSpace.SDK was not created: ' + url));
                }
            };
            script.onerror = function() {
                reject(new Error('Cannot load ONLYOFFICE SDK: ' + url));
            };
            document.head.appendChild(script);
        });
        return loaded[url];
    }

    function loadSdk(src, configuredVersion) {
        if (window.DocSpace && window.DocSpace.SDK) {
            return Promise.resolve('already-loaded');
        }
        var urls = sdkUrls(src, configuredVersion);
        var index = 0;
        var errors = [];
        function next() {
            if (index >= urls.length) {
                return Promise.reject(new Error(errors.join(' | ') || 'ONLYOFFICE SDK could not be loaded.'));
            }
            var url = urls[index++];
            return loadOne(url).catch(function(error) {
                errors.push(error.message);
                return next();
            });
        }
        return next();
    }

    function shell(config) {
        var frame = document.getElementById(config.frameId);
        if (!frame) {
            return null;
        }
        return frame.closest('.wsg-docspace-shell') || frame.parentElement;
    }

    function setStatus(config, message, type) {
        if (config.showRuntimeStatus !== true) {
            return;
        }
        var wrapper = shell(config);
        if (!wrapper) {
            return;
        }
        var status = wrapper.querySelector('[data-docspace-status]');
        if (!status) {
            return;
        }
        status.className = 'wsg-docspace-runtime-status alert py-2 mb-2 alert-' + (type || 'info');
        status.textContent = message;
        status.hidden = false;
    }

    function destroy(config) {
        var instance = active[config.frameId];
        if (instance && typeof instance.destroyFrame === 'function') {
            try {
                instance.destroyFrame();
            } catch (ignore) {
                // The SDK may already have replaced/destroyed the iframe.
            }
        }
        delete active[config.frameId];
        var container = document.getElementById(config.frameId);
        if (container) {
            container.innerHTML = '';
        }
    }

    function fallback(config, message) {
        var container = document.getElementById(config.frameId);
        if (!container) {
            return;
        }
        if (config.showRuntimeStatus === true) {
            setStatus(config, message || config.errorMessage || 'ONLYOFFICE could not be loaded.', 'danger');
        }
        if (config.showErrorNotices !== true) {
            return;
        }
        if (!container.querySelector('.wsg-docspace-fallback-note')) {
            var note = document.createElement('div');
            note.className = 'alert alert-danger wsg-docspace-fallback-note';
            note.textContent = config.authMode === 'shared-editor' ?
                'Không thể mở ONLYOFFICE Editor. Hãy kiểm tra Folder ID, requestToken và quyền Editing của Public Room.' :
                'Không thể mở ONLYOFFICE. Hãy thử tải lại hoặc kiểm tra quyền truy cập DocSpace.';
            container.appendChild(note);
        }
    }

    function events(config, readonly, publicRoom) {
        return {
            onAppReady: function() {
                if (publicRoom) {
                    setStatus(config,
                        'Public Room đã được xác thực bằng token của phòng; đang tải không gian chỉnh sửa…', 'info');
                    return;
                }
                var scope = config.tokenScope === 'shared-room' ? 'requestToken Public Room dùng chung' : (config.tokenScope === 'room' ? 'token Public Room riêng' : 'token tài liệu');
                setStatus(config, readonly ?
                    'ONLYOFFICE Viewer đã sẵn sàng (' + scope + ').' :
                    'ONLYOFFICE Editor đã khởi tạo (' + scope + ').', 'info');
            },
            onContentReady: function() {
                if (publicRoom) {
                    setStatus(config,
                        'Phòng chỉnh sửa đã sẵn sàng. Mở tệp duy nhất trong phòng để vào ONLYOFFICE Editor đầy đủ; ' +
                        'sau đó Home/Insert/Layout và nhập liệu sẽ hoạt động.', 'success');
                    return;
                }
                setStatus(config, readonly ?
                    'Đã tải xong tài liệu ở chế độ chỉ xem.' :
                    'Đã tải xong tài liệu. Nếu không nhập được, phiên DocSpace hiện tại chưa có xác thực Editor trực tiếp.',
                    readonly ? 'success' : 'warning');
            },
            onAuthSuccess: function() {
                setStatus(config, publicRoom ?
                    'DocSpace đã xác thực Public Room của bài làm.' :
                    'DocSpace đã xác thực phiên tài liệu.', 'info');
            },
            onEditorOpen: function() {
                setStatus(config, 'ONLYOFFICE Editor đầy đủ đã mở và có thể soạn thảo.', 'success');
            },
            onNoAccess: function() {
                fallback(config, publicRoom ?
                    'DocSpace từ chối quyền Public Room. Hãy kiểm tra room link đang có access=2 (Editing).' :
                    'DocSpace từ chối quyền truy cập tài liệu.');
            },
            onNotFound: function() {
                fallback(config, 'Tài liệu hoặc Public Room DocSpace không còn tồn tại.');
            },
            onAppError: function(error) {
                var detail = error && (error.message || error.error || error.data) ?
                    String(error.message || error.error || error.data) : 'Unknown DocSpace error';
                fallback(config, 'ONLYOFFICE báo lỗi: ' + detail);
            }
        };
    }

    function editorConfig(config, src) {
        var readonly = config.mode === 'viewer' || config.readonly === true;
        return {
            frameId: config.frameId,
            src: src,
            id: String(config.id),
            roomId: String(config.roomId || ''),
            requestToken: String(config.requestToken || ''),
            mode: readonly ? 'viewer' : 'editor',
            editorType: 'desktop',
            width: config.width || '100%',
            height: config.height || '920px',
            locale: config.locale || 'vi-VN',
            theme: config.theme || 'System',
            showHeader: true,
            showMenu: true,
            showTitle: true,
            showSignOut: false,
            checkCSP: true,
            editorGoBack: false,
            editorCustomization: {
                anonymous: {
                    request: false,
                    label: config.userLabel || 'Moodle user'
                },
                features: {
                    spellcheck: true,
                    comments: true,
                    chat: true,
                    review: true
                }
            },
            events: events(config, readonly, false)
        };
    }

    function workspaceConfig(config, src) {
        var roomId = String(config.roomId || '');
        return {
            frameId: config.frameId,
            src: src,
            id: roomId,
            roomId: roomId,
            requestToken: String(config.requestToken || ''),
            mode: 'public-room',
            rootPath: '/rooms/shared/' + roomId,
            filter: {folder: roomId},
            width: config.width || '100%',
            height: config.height || '920px',
            locale: config.locale || 'vi-VN',
            theme: config.theme || 'System',
            showHeader: false,
            showMenu: false,
            showTitle: true,
            showSignOut: false,
            showSettings: false,
            withSearch: false,
            withBreadCrumbs: false,
            checkCSP: true,
            events: events(config, false, true)
        };
    }

    function startEditor(config, src) {
        var sdk = window.DocSpace.SDK;
        var sdkConfig = editorConfig(config, src);
        destroy(config);
        // Use the dedicated editor entry point first. This avoids the Manager
        // default and makes DocSpace request the full desktop editor explicitly.
        if (sdkConfig.mode === 'viewer' && typeof sdk.initViewer === 'function') {
            active[config.frameId] = sdk.initViewer(sdkConfig);
        } else if (sdkConfig.mode === 'editor' && typeof sdk.initEditor === 'function') {
            active[config.frameId] = sdk.initEditor(sdkConfig);
        } else if (typeof sdk.initFrame === 'function') {
            active[config.frameId] = sdk.initFrame(sdkConfig);
        } else if (typeof sdk.init === 'function') {
            active[config.frameId] = sdk.init(sdkConfig);
        } else {
            throw new Error('Loaded SDK has no compatible editor method.');
        }
    }

    function startWorkspace(config, src) {
        var sdk = window.DocSpace.SDK;
        var sdkConfig = workspaceConfig(config, src);
        destroy(config);
        // Public-room is the ONLYOFFICE-supported no-login editing mode.
        // Standalone Editor mode requires an authenticated DocSpace user/session.
        if (typeof sdk.initFrame === 'function') {
            active[config.frameId] = sdk.initFrame(sdkConfig);
        } else if (typeof sdk.initManager === 'function') {
            // Authentication docs explicitly support initManager + room requestToken.
            active[config.frameId] = sdk.initManager(sdkConfig);
        } else if (typeof sdk.init === 'function') {
            active[config.frameId] = sdk.init(sdkConfig);
        } else {
            throw new Error('Loaded SDK has no compatible Public Room method.');
        }
        setStatus(config,
            'Đang mở Public Room có quyền Editing. Mỗi phòng chỉ chứa một phiếu/bài làm.', 'info');
    }

    function initialise(config) {
        var src = String(config.src || '').replace(/\/$/, '');
        if (!src || !config.id || !config.requestToken) {
            fallback(config, 'Thiếu URL, file ID hoặc request token Public Room của DocSpace.');
            return;
        }
        setStatus(config, 'Đang tải ONLYOFFICE…', 'info');
        loadSdk(src, config.sdkVersion).then(function(url) {
            if (config.readonly === true || config.mode === 'viewer') {
                startEditor(config, src);
                setStatus(config, 'SDK đã tải từ ' + url + '; đang mở Viewer…', 'info');
                return;
            }
            if (config.authMode === 'public-room' || config.tokenScope === 'room') {
                startWorkspace(config, src);
                setStatus(config,
                    'SDK đã tải từ ' + url + '; dùng Public Room vì tài khoản Moodle không cần đăng nhập DocSpace.',
                    'info');
                return;
            }
            startEditor(config, src);
            setStatus(config, config.authMode === 'shared-editor' ?
                'SDK đã tải từ ' + url + '; đang mở Editor trực tiếp bằng requestToken của Public Room dùng chung…' :
                'SDK đã tải từ ' + url + '; đang mở Editor bằng phiên DocSpace đã xác thực…', 'info');
        }).catch(function(error) {
            fallback(config, error.message);
        });
    }

    function bindToolbar(config) {
        var wrapper = shell(config);
        if (!wrapper) {
            return;
        }
        var fullscreen = wrapper.querySelector('[data-docspace-fullscreen]');
        if (fullscreen && fullscreen.dataset.bound !== '1') {
            fullscreen.dataset.bound = '1';
            fullscreen.addEventListener('click', function() {
                if (wrapper.requestFullscreen) {
                    wrapper.requestFullscreen();
                }
            });
        }
        var retry = wrapper.querySelector('[data-docspace-retry-editor]');
        if (retry && retry.dataset.bound !== '1') {
            retry.dataset.bound = '1';
            retry.addEventListener('click', function() {
                initialise(config);
            });
        }
        var workspace = wrapper.querySelector('[data-docspace-workspace]');
        if (workspace && workspace.dataset.bound !== '1') {
            workspace.dataset.bound = '1';
            workspace.addEventListener('click', function() {
                var src = String(config.src || '').replace(/\/$/, '');
                loadSdk(src, config.sdkVersion).then(function() {
                    startWorkspace(config, src);
                }).catch(function(error) {
                    fallback(config, error.message);
                });
            });
        }
    }

    return {
        init: function(configs) {
            if (!Array.isArray(configs)) {
                configs = [configs];
            }
            configs.forEach(function(config) {
                bindToolbar(config);
                initialise(config);
            });
        },
        diagnose: function(config) {
            var target = document.getElementById(config.targetId);
            var src = String(config.src || '').replace(/\/$/, '');
            if (!target) {
                return;
            }
            target.className = 'alert alert-info';
            target.textContent = 'Đang kiểm tra SDK trình duyệt…';
            loadSdk(src, config.sdkVersion).then(function(url) {
                var sdk = window.DocSpace && window.DocSpace.SDK;
                var methods = ['initFrame', 'init', 'initEditor', 'initViewer', 'initManager'].filter(function(name) {
                    return sdk && typeof sdk[name] === 'function';
                });
                target.className = 'alert alert-success';
                target.textContent = 'SDK trình duyệt tải thành công từ ' + url + '. Hàm khả dụng: ' + methods.join(', ');
            }).catch(function(error) {
                target.className = 'alert alert-danger';
                target.textContent = error.message;
            });
        }
    };
});
