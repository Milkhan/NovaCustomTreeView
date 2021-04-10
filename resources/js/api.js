export default {
    getResource() {
        return window.axios
            .post('/nova-vendor/tree-view/get-resource')
            .then(response => response.data);
    },
};
