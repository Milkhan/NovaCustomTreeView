<template>
  <loading-view :loading="loading">
    <div class="flex">
      <v-jstree
        :data="tree"
        :draggable="false"
        whole-row
        @item-drop="dragEnded"
        @item-click="itemClick"
        :multiple="false"
        :collapse="true"
      ></v-jstree>
      <TreeDetails
        v-if="currentNode"
        :resource="resource"
        :node="currentNode"
        @onOrder="orderNode"
        @onDelete="deleteNode"
        @onToggle="toggleNode"
      />
    </div>
  </loading-view>
</template>

<script>
import TreeDetails from "./TreeDetails";
import VJstree from "vue-jstree";
import api from "../api";

export default {
  props: ["resource"],
  data() {
    return {
      loading: true,
      tree: {},
      parents: {},
      lastRetrievedAt: 0,
      currentNode: null,
    };
  },
  methods: {
    getData() {
      api.getResource().then((result) => {
        console.log("result", result);
      });
    },
    parseResources(rows) {
      const tree = {};
      const map = {};
      if (!rows) return;
      console.log(rows);
      rows.forEach((row) => {
        console.log("row", row);
        let leaf = map[row.id]={
          id: row.id,
          text: row.name,
          is_active: row.is_active,
          icon: this.isActiveIcon(true, true),
          order: row.order,
          score: row.score,
          parentText: row.parent,
          parentId: row.parent_id,
          selected: false,
          children: [],
          opened: true,
          size: "small",
        };
        // console.log('leaf', leaf)
      });
      console.log('rows', rows)
      rows.forEach((row) => {
        let leaf = map[row.id]
        // console.log(leaf)
         console.log('leaf.parentId', leaf.parentId)
        if (leaf.parentId) {
          if (map[leaf.parentId]) {
            let parent = map[leaf.parentId];
            if (!parent.children) parent.children = [leaf];
            else parent.children.push(leaf);
            this.order(parent.children);
          }
        } else tree[row.id] = leaf;
      });
      //  console.log(rows)
      // rows.forEach(row => {
      //      console.log('row', row)
      //         let leaf = map[row.id.value] = {
      //             id: row.id.value,
      //             text: this.attrValue(row, 'name'),
      //             is_active: this.attrValue(row, 'is_active'),
      //             icon: this.isActiveIcon(true, true),
      //             order: this.attrValue(row, 'order'),
      //             score: this.attrValue(row, 'score'),
      //             parentText: this.attrValue(row, 'parent'),
      //             parentId: this.attrValue(row, 'parent', 'belongsToId'),
      //             selected:false,
      //             children: [],
      //             opened: true,
      //             size:'small'
      //         }
      //     });
      // rows.forEach(row => {
      //     let leaf = map[row.id.value]
      //     // console.log(leaf)
      //     // console.log(leaf.parentId)
      //     if (leaf.parentId) {
      //         if (map[leaf.parentId]) {
      //             let parent = map[leaf.parentId]
      //             if(!parent.children) parent.children = [leaf]
      //             else parent.children.push(leaf)
      //             this.order(parent.children)
      //         }
      //     } else tree[row.id.value] = leaf
      // })
      this.parents = map;
      this.tree = tree;
      Object.values(tree).forEach((root) =>
        this.setIconState(root.children, root.is_active)
      );
    },
    attrValue(row, attr, prop = "value") {
      const field = row.fields.find((f) => f.attribute === attr);
      return field && field[prop];
    },
    itemClick(node) {
      console.log("tiklandi");
      //  this.getData();
      // console.log('node.model', node.model)
      this.currentNode = node.model;
    },
    dragEnded(node, item, draggedItem, e) {
      // console.log( 'node')
      // console.log( node)
      // console.log( item)
      // console.log( 'draggedItem', draggedItem)
      // console.log(  e)
      this.updateParent(draggedItem, item);
    },
    formData(node, fields, method) {
      const formData = new FormData();
      const nodeFields = {
        order: node.order,
        title: node.text,
        ...fields,
      };
      for (let attr in nodeFields) {
        formData.append(attr, nodeFields[attr]);
      }
      formData.append("_method", method);
      formData.append("_retrieved_at", this.lastRetrievedAt);
      return formData;
    },
    updateNode(node, fields = {}) {
      return Nova.request()
        .post(
          `/nova-api/${this.resource}/${node.id}?editing=true&editMode=update`,
          this.formData(node, fields, "PUT")
        )
        .then((res) => {
          this.mergeResponse(res.data);
        });
    },
    updateParent(node, newParent) {
      const oldParent = node.parentId && this.parents[node.parentId];
      // console.log('newParent.id', newParent.id);
      return this.updateNode(node, {
        order: 0,
        parent: newParent.id,
      }).catch((err) => {
        // console.log('err', err)
        newParent.children.splice(newParent.children.indexOf(node), 1);
        if (oldParent) {
          oldParent.children.push(node);
          this.order(oldParent.children);
        }
      });
    },
    fetchData() {
      this.loading = true;
      api
        .getResource()
        .then((res) => {
          this.currentNode = null;
          console.log("res.data", res.data);
          this.parseResources(res.data);
          this.updateRetrievedAt();
        })
        .catch((err) => {
          // console.error(err)
          this.$router.replace("/404");
        })
        .then(() => (this.loading = false));
      // Nova.request().get(`/nova-api/${this.resource}?per_page=100`)
      //     .then(res => {
      //         this.currentNode = null
      //         // console.log('res.data', res.data)
      //         this.parseResources(res.data.resources)
      //         this.updateRetrievedAt()
      //     }).catch(err => {
      //         // console.error(err)
      //         this.$router.replace('/404')
      //     }).then(() => this.loading = false);
    },
    setIconState(children = [], parentActive = true) {
      Array.isArray(children) &&
        children.forEach((child) => {
          child.icon = this.isActiveIcon(child.is_active, parentActive);
          this.setIconState(child.children, child.icon === "enabled");
        });
    },
    isActiveIcon(active, parentActive) {
      return !active ? "disabled" : parentActive ? "enabled" : "parentDisabled";
    },
    order(nodes) {
      nodes.sort((a, b) => (a.order > b.order ? 1 : -1));
    },
    updateRetrievedAt() {
      this.lastRetrievedAt = Math.floor(Date.now() / 1000);
    },
    deleteNode() {
      Nova.request()
        .post(
          `/nova-api/${this.resource}`,
          this.formData(
            1,
            this.currentNode,
            {
              resources: this.currentNode.id,
            },
            "DELETE"
          )
        )
        .then((res) => {
          let parent = this.parents[this.currentNode.parentId];
          parent.children.splice(parent.children.indexOf(this.currentNode), 1);
          this.currentNode = null;
        })
        .catch((err) => {
          console.error(err);
          let parent = this.parents[this.currentNode.parentId];
          parent.children.splice(parent.children.indexOf(this.currentNode), 1);
          this.currentNode = null;
          // node.is_active = activeState
          // this.$toasted.show('Failed toggling resource!', { type: 'error' })
        });
    },
    toggleNode() {
      // console.log('toggle')
      const node = this.currentNode;
      const activeState = node.is_active;
      node.is_active = "loading";

      this.updateNode(node, {
        is_active: !activeState ? 1 : 0,
      }).catch((err) => {
        // console.error(err)
        node.is_active = activeState;
        this.$toasted.show("Failed toggling resource!", { type: "error" });
      });
    },
    orderNode(dir) {
      const node = this.currentNode;
      // console.log('node', node)
      // console.log('dir', dir)
      let order = (node.order * 1 || 0) + dir;
      if (order < 0) order = 0;
      if (node.parentId && this.parents[node.parentId].children) {
        const cLen = this.parents[node.parentId].children.length;
        if (order > cLen) order = cLen;
      }
      // console.log('order', order)
      this.updateNode(node, {
        order,
      }).catch((err) => {
        this.$toasted.show("Failed changing order", { type: "error" });
      });
    },
    mergeResponse(res) {
      if (res.id && res.resource) {
        const { id, parent_id, title, order, is_active } = res.resource;
        const node = this.parents[id];
        const parent = this.parents[parent_id];
        node.parentText = parent.text;
        node.parentId = parent_id;
        node.text = title;
        node.is_active = is_active;
        node.icon = this.isActiveIcon(is_active, parent.icon === "enabled");
        node.order = order;
        this.order(parent.children);
        this.setIconState(node.children, node.icon === "enabled");
        this.updateRetrievedAt();
      }
    },
  },
  watch: {
    resource: {
      immediate: true,
      handler() {
        this.fetchData();
      },
    },
  },
  components: {
    VJstree,
    TreeDetails,
  },
};
</script>

<style lang="scss">
button:focus {
  outline: none;
}
.tree-default .tree-themeicon-custom {
  border: none;
  border-radius: 100px;
  box-shadow: 0 0 0 4px white inset;
  &.enabled {
    background: #252d37;
  }
  &.disabled {
    background: #7b8ca3;
  }
  &.parentDisabled {
    background: orange;
  }
}
</style>
