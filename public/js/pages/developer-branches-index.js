(() => {
    window.generateBranchModal = function generateBranchModal(item) {
        const data = JSON.parse(item.dataset.json || "{}");

        createModal({
            id: "branchDetailsModal",
            uId: data.id,
            status: data.status,
            image: data.image,
            name: data.name,
            details: data.details || {},
            profile: true,
            bottomActions: [
                {
                    id: "manage-branch",
                    text: "Manage Branch",
                    onclick: `window.location.href = ${JSON.stringify(data.manage_url)}`,
                },
                {
                    id: "edit-branch",
                    text: "Edit",
                    onclick: `window.location.href = ${JSON.stringify(data.edit_url)}`,
                },
            ],
        });
    };
})();
