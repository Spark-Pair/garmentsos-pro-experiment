(() => {
    function initUsersIndex(config) {
        const currentUserRole = config?.currentUserRole;
        const authLayout = config?.authLayout;
        const updateUserStatusUrl = config?.updateUserStatusUrl;
        const resetPasswordUrl = config?.resetPasswordUrl;

        if (authLayout) {
            window.authLayout = authLayout;
        }

        window.createRow = function(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group grid grid-cols-5 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="text-left pl-5 col-span-2">${data.name}</span>
                <span class="text-left pl-5">${data.username}</span>
                <span class="text-center capitalize">${data.role}</span>
                <span class="text-right pr-5 capitalize ${data.status === 'active' ? 'text-[var(--border-success)]' : 'text-[var(--border-error)]'}">${data.status}</span>
            </div>`;
        }

        window.generateContextMenu = function(e) {
            e.preventDefault();
            let item = e.target.closest('.item');
            let data = JSON.parse(item.dataset.json);

            let contextMenuData = {
                data: data,
                x: e.pageX,
                y: e.pageY,
                action: updateUserStatusUrl,
            };

            if (currentUserRole != data.details['Role']) {
                contextMenuData.forceStatusBtn = true;
            }

            if ((currentUserRole == 'admin' || currentUserRole == 'developer' || currentUserRole == 'owner') && currentUserRole != data.details['Role']) {
                contextMenuData.actions = [
                    {id: 'reset-password', text: 'Reset Password', onclick: `generateResetPasswordModel(${JSON.stringify(data)})`},
                ];
            }

            createContextMenu(contextMenuData);
        }

        window.generateModal = function(item) {
            let data = JSON.parse(item.dataset.json);

            let modalData = {
                id: 'modalForm',
                uId: data.id,
                status: data.status,
                method: "POST",
                action: updateUserStatusUrl,
                image: data.image,
                name: data.name,
                details: {
                    'Username': data.details['Username'],
                    'Role': data.details['Role'],
                },
                profile: true,
            }

            if (currentUserRole != data.details['Role']) {
                modalData.forceStatusBtn = true;
            }

            if ((currentUserRole == 'admin' || currentUserRole == 'developer' || currentUserRole == 'owner') && currentUserRole != data.details['Role']) {
                modalData.bottomActions = [
                    {id: 'reset-password', text: 'Reset Password', onclick: `generateResetPasswordModel(${JSON.stringify(data)})`},
                ];
            }

            createModal(modalData);
        }

        window.generateResetPasswordModel = function(data) {
            let modalData = {
                id: 'resetPasswordModalForm',
                class: 'h-auto',
                method: 'POST',
                action: resetPasswordUrl,
                name: 'Reset Password',
                fields: [
                    {
                        category: 'input',
                        label: 'Username',
                        value: data.details['Username'],
                        disabled: true,
                    },
                    {
                        category: 'input',
                        type: 'hidden',
                        name: 'user_id',
                        value: data.id,
                    },
                    {
                        category: 'input',
                        label: 'Password',
                        name: 'password',
                        id: 'password',
                        type: 'password',
                        placeholder: 'Enter new password',
                        data_validate: 'required|min:4|alphanumeric|lowercase',
                        required: true,
                    },
                ],
                fieldsGridCount: '2',
                bottomActions: [
                    {id: 'reset-password-btn', text: 'Reset Password', type: 'submit'}
                ]
            }

            createModal(modalData);
        }
    }

    window.initUsersIndex = initUsersIndex;

    function boot() {
        if (window.__usersIndex) {
            initUsersIndex(window.__usersIndex);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
