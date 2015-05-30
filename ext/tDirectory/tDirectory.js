/**
 * @author: Michael Kirchner
 * @author: Mario gleichmann
 *@description: The tDirectory is a extension for the tUploader to handle Directory operations.
 */
tUploader.extend({
    domBreadcrumb: null,
    domDirectoryContext: null,
    domProgressBar: null,
    domLog: null,
    /**
     *
     * @param triggerDomObject
     *  is the dom element that triggered the request.
     * @returns {XMLHttpRequest}
     */
    getXhr: function (triggerDomObject) {
        var xhr = new XMLHttpRequest();
        xhr.domBreadcrumb = this.domBreadcrumb;
        xhr.domDirectoryContext = this.domDirectoryContext;
        xhr.domLog = this.domLog;
        xhr.template = this.template;
        xhr.triggerDomObject = triggerDomObject;

        return xhr;
    },
    itemDelete: function (self, path, name) {
        if (confirm('Realy remove \'' + path + '/' + name + '\'?') == true) {
            var xhr = this.getXhr(self);
            xhr.open('GET', 'delete.json?name=' + name + '&path=' + path, true);
            xhr.onload = function () {
                var response = JSON.parse(xhr.response);
                if (response.success == true) {
                    var listItem = xhr.triggerDomObject.parentNode;
                    listItem.parentNode.removeChild(listItem);
                    tUploader.log('Folder deleted.');
                } else {
                    tUploader.log('Folder is not empty.');
                }
            };
            xhr.send();
        } else {
            return false;
        }
    },
    addFolder: function (self, path) {
        var newFolder = prompt("Folder name:");

        if (newFolder != "") {
            var xhr = this.getXhr(self);
            xhr.open('GET', 'create.json?name=' + newFolder + '&path=' + path, true);
            xhr.onload = function () {
                var response = JSON.parse(xhr.response);
                var listItem = document.createElement('LI');
                listItem.innerHTML = tUploader.template.buildItem(response.path, response.name, response.type);
                var directory = tUploader.domDirectoryContext.children[0].children[0];
                directory = directory.children[directory.children.length - 1];
                if (response.success == true) {
                    if (!response.overwrite) {
                        if (directory.children.length > response.order) {
                            directory.insertBefore(listItem, directory.children[response.order]);
                        } else {
                            directory.appendChild(listItem);
                        }
                    }
                    tUploader.log('Folder created.');
                } else {
                    tUploader.log('Folder already exist.');
                }
            };
            xhr.send();
        } else {
            return false;
        }
    },
    template: {
        log: '<h2>your uploadLog</h2>',
        btnCreateDirectory: '<a onclick=\'tUploader.addFolder(this, "{{PATH}}");\' title="create folder"><i class="glyphicon glyphicon-plus-sign"></i></a>',
        breadcrumbRoot: '<a href="/" title="/"><i class="glyphicon glyphicon-folder-open" aria-hidden="true"></i></a>' +
        '<a href="/" title="/"><span class="folder top" style="margin: 0 5px;">root</span></a>',
        breadcrumbPart: '/ <a href="{{PARENT_PATH}}/" title="{{PARENT_PATH}}"><span class="folder top">{{PathName}}</span></a>',
        itemDirectory: '<li><a href="{{PATH}}" title="go into folder"><i class="glyphicon glyphicon-folder-close" aria-hidden="true"></i></a>' +
        '&nbsp;<a href="{{PATH}}" title="go into folder"><span class="folder">{{NAME}}</span></a>{{DOM_DOWNLOAD}}{{DOM_DELETE}}</li>',
        itemFile: '<li><a target="_blank" href="{{PATH}}" title="download file"><i class="glyphicon glyphicon-file" aria-hidden="true"></i></a>' +
        '&nbsp;<a href="{{PATH}}" title="go into folder"><span class="file">{{NAME}}</span></a>{{DOM_DOWNLOAD}}{{DOM_DELETE}}</li>',
        btnDownload: '<a href="download.json?name={{NAME}}" title="download" data-type="file"><i class="glyphicon glyphicon-download" aria-hidden="true"></i></a>',
        btnDelete: '<a onclick=\'tUploader.itemDelete(this, "{{PATH}}", "{{NAME}}");\'>' +
        '<i class="glyphicon glyphicon-remove" aria-hidden="true"></i></a>\n',
        buildBreadcrumb: function (pathList) {
            return '';
        },
        build: function (path, pathList, fileList) {
            var renderedTemplate = '<ul><li>';
            renderedTemplate += this.breadcrumbRoot;

            var sParentPath = '';
            for (var n = 0; n < pathList.length; n++) {
                sParentPath += '/' + pathList[n];
                renderedTemplate += this.breadcrumbPart.split('{{PARENT_PATH}}').join(sParentPath).split('{{PathName}}').join(pathList[n]);
            }

            renderedTemplate += this.btnCreateDirectory.split('{{PATH}}').join(path);

            renderedTemplate += '<ul>';

            for (var i = 1; i < fileList.length; i++) {
                renderedTemplate += this.buildItem(path, fileList[i][1], fileList[i][0]);
            }

            renderedTemplate += '</ul></li></ul>';

            return renderedTemplate;
        },
        buildLog: function () {
            return this.log;
        },
        buildItem: function (path, fileName, fileType) {
            var renderedTemplate = '';
            switch (fileType) {
                case 'dir':
                    var simplyFydDirectoryPath = this.simplifyPath([path, fileName], true, true);
                    renderedTemplate += this.itemDirectory.split('{{PATH}}').join(simplyFydDirectoryPath).split('{{NAME}}').join(fileName);
                    break;
                case 'file':
                default:
                    var simplyFydFilePath = this.simplifyPath([path, fileName], true);
                    renderedTemplate += this.itemFile.split('{{PATH}}').join(simplyFydFilePath).split('{{NAME}}').join(fileName);
            }

            renderedTemplate = renderedTemplate.replace('{{DOM_DOWNLOAD}}', this.btnDownload.split('{{NAME}}').join(this.simplifyPath([path, fileName])));
            return renderedTemplate.replace('{{DOM_DELETE}}', this.btnDelete.split('{{PATH}}').join(this.simplifyPath([path], true)).split('{{NAME}}').join(this.simplifyPath([fileName])));
        },
        simplifyPath: function (aPathParts, aStart, aEnd) {
            var sStart = typeof aStart === 'undefined' || aStart == false ? '' : '/';
            var sEnd = typeof aEnd === 'undefined' || aEnd == false ? '' : '/';

            var sPath = (sStart + aPathParts.join('/') + sEnd).replace('//', '/');
            if (sStart == '' && sPath.substr(0, 1) == '/') {
                sPath = sPath.substr(1);
            }
            if (sEnd == '' && sPath.substr(-1, 1) == '/') {
                sPath = sPath.substr(0, -1);
            }
            return sPath;
        }
    },
    reloadDirectory: function (_callback, insertIntoCurrentDom) {
        try {
            var xhr = this.getXhr(this);
            xhr.open('GET', 'uploads.json', true);
            xhr.onload = function () {
                var files = JSON.parse(xhr.response);
                if (files != null && files instanceof Array) {
                    var path = (files[0] != null ? files[0] : "");
                    if (path == ".") {
                        uri = new URI(document.location.href);
                        path = uri.directory();
                    }

                    if (insertIntoCurrentDom || insertIntoCurrentDom == undefined) {
                        if (this.domBreadcrumb) this.domBreadcrumb.innerHtml = this.buildBreadcrumb(path.split('/'));
                        if (this.domDirectoryContext) this.domDirectoryContext.innerHTML = this.template.build(path, path.split('/'), files);
                        if (this.domLog) this.domLog.innerHTML = this.template.buildLog();
                    } else if (_callback) {
                        _callback({
                            breadcrumb: this.buildBreadcrumb(path.split('/')),
                            directory: this.template.build(path, path.split('/'), files),
                            log: this.template.build(path, path.split('/'), files)
                        });
                    }
                }
            };
            xhr.send();
        } catch (e) {
            if (e.code == 19) {
                console.log("This html file should not be load directly. It will be load by upload.php. " +
                "Please use a web server or 'php -S 0.0.0.0:8080 index.php'.");
            }
        }
    },
    log: function (message) {
        if (tUploader.domLog) {
            tUploader.domLog.innerHTML += '<br><span class="status success">' + message + '</span>';
        }
    },
    onProgress: function(params) {
        document.getElementById("speed").innerHTML = params.bitrate ? (params.bitrate) + 'kb/s' : "";
        tUploader.domProgressBar.style.width = parseInt(params.globalProgress * 100) + '%';
    },
    onError: function(params) {
        if(params) {
            tUploader.log('Error on uploading');
        }
    },
    onSuccess: function(params) {
        var response = params.response;
        tUploader.log('Uploaded successful finished');
        var listItem = document.createElement('LI');
        listItem.innerHTML = tUploader.template.buildItem(response.path, response.name, response.type);
        var directory = tUploader.domDirectoryContext.children[0].children[0];
        directory = directory.children[directory.children.length - 1];
        if(!response.overwrite) {
            if(directory.children.length > response.order) {
                directory.insertBefore(listItem, directory.children[response.order]);
            } else {
                directory.appendChild(listItem);
            }
            tUploader.domProgressBar.style.width = '0%';
        }
    },
    onBegin:function(params) {
        tUploader.log('Upload started');
    }
});

tUploader.on("progress", function (params) { tUploader.onProgress(params); });

tUploader.on("error", function (params) { tUploader.onError(params); });

tUploader.on("success", function (params) {
    params.response = JSON.parse(params.response);
    tUploader.onSuccess(params);
});

tUploader.preprocess = function (e, callback) { callback(); };

tUploader.on("begin", function (params) { tUploader.onBegin(params); });
