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
import Vue from "vue";

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
    parseResources(rows) {
      //   console.log(`rows`, rows);
      const tree = [];
      const map = {};
      if (!rows) return;
      // console.log(rows)
      rows.forEach((row) => {
        // console.log(row)
        let leaf = (map[row.id.value] = {
          id: row.id.value,
          text: this.attrValue(row, "name"),
          is_active: this.attrValue(row, "is_active"),
          icon: this.isActiveIcon(true, true),
          order: this.attrValue(row, "order"),
          score: this.attrValue(row, "score"),
          parentText: this.attrValue(row, "parent"),
          parentId: this.attrValue(row, "parent", "belongsToId"),
          selected: false,
          children: [],
          opened: true,
        });
      });
      let treeNo = 0;
      rows.forEach((row) => {
        treeNo++;
        let leaf = map[row.id.value];
        // console.log(leaf);
        // console.log(leaf.parentId)
        if (leaf.parentId) {
          if (map[leaf.parentId]) {
            let parent = map[leaf.parentId];
            if (!parent.children) parent.children = [leaf];
            else parent.children.push(leaf);
            this.order(parent.children);
          }
        } else {
          // tree[row.id.value] = leaf
          // tree[treeNo] = leaf
          tree.push(leaf);
        }

        // this.order(tree)
      });
      tree.sort((a, b) => a.order - b.order);
    //   console.log(`tree`, typeof tree, tree);
      // const treeSorted = Object.keys(tree).sort((a,b)=>{ a.order-b.order})
      // console.log(`treeSorted`, treeSorted)
      // var treeSorted = tree.slice(0);
      // byDate.sort(function(a,b) {
      //     return a.order - b.order;
      // });
      // // this.tree=treeSorted;
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
      // console.log('tiklandi')
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
      // Request URL: https://acme.martialup.com/nova-api/skills/14?viaResource=&viaResourceId=&viaRelationship=&editing=true&editMode=update
      // Request URL: https://acme.martialup.com/nova-api/skills/18?viaResource=&viaResourceId=&viaRelationship=&editing=true&editMode=update

      //   console.log(`node`, node);
      //   console.log(`fields`, fields);
      //   console.log(`fields`, fields["order"]);
      const newObj = {
        id: node.id,
        score: node.score,
        name: node.text,
        description: node.description,
        parent_id: node.parentId,
        order: fields["order"],
        order: fields["order"],
      };
    //   console.log(`node.parentId`, node.parentId);
      //   console.log(`newObj`, newObj);
      return Nova.request()
        .post(
          `/nova-api/${this.resource}/${node.id}?viaResource=&viaResourceId=&viaRelationship=&editing=true&editMode=update`,
          this.formData(1, newObj, "PUT")
        )
        .then((res) => {
        //   console.log(`res.data`, res.data);
          this.mergeResponse(res.data, node.parentId);
        });
    },
    updateParent(node, newParent) {
      const oldParent = node.parentId && this.parents[node.parentId];
      //   console.log('newParent.id', newParent.id);
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
      Nova.request()
        .get(`/nova-api/${this.resource}`)
        .then((res) => {
          this.currentNode = null;
          // console.log('res.data', res.data)
          this.parseResources(res.data.resources);
          this.updateRetrievedAt();
        })
        .catch((err) => {
          // console.error(err)
          this.$router.replace("/404");
        })
        .then(() => (this.loading = false));
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
        .delete(`/nova-api/${this.resource}?resources[]=${this.currentNode.id}`)
        .then((res) => {
          let parent = this.parents[this.currentNode.parentId];
          if (parent) {
            parent.children.splice(
              parent.children.indexOf(this.currentNode),
              1
            );
          } else {
            for (const [key, value] of Object.entries(this.tree)) {
              if (value.id === this.currentNode.id) {
                Vue.delete(this.tree, key);
              }
            }
          }
          this.currentNode = null;
          this.$toasted.show("The skill is deleted!", { type: "success" });
        })
        .catch((err) => {
          console.error("err");
          console.error(err);
          //   let parent = this.parents[this.currentNode.parentId]
          //     if(parent) parent.splice(parent.children.indexOf(this.currentNode), 1)
          //     this.currentNode = null
          // node.is_active = activeState
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
    //   console.log("node", node);
    //   console.log("dir", dir);
      let order = (node.order * 1 || 0) + dir;
      if (order < 0) order = 0;
      if (node.parentId && this.parents[node.parentId].children) {
        const cLen = this.parents[node.parentId].children.length;
        if (order > cLen) order = cLen;
      }
    //   console.log("order", order);
      this.updateNode(node, {
        order: order,
      }).catch((err) => {
        // console.log(`err`, err);
        this.$toasted.show("Failed changing order", { type: "error" });
      });
    },
    mergeResponse(res, isParent = true) {
    //   console.log(`isParent`, isParent);
      if (res.id && res.resource) {
        // console.log(`res.resource.parent_id`, res.resource.parent_id);
        // console.log(`res.parent_id`, res.parent_id);
        // console.log(`res.resource`, res.resource);
        if (!isParent) {
          //   console.log(`res.resource`, res.resource);
          const { id, parent_id, name, order, is_active } = res.resource;
          //   console.log(`name`, name)
          const node = this.tree.find((tr) => tr.id == id);
          // const parent = this.parents[parent_id]
        //   console.log(`parentas00000`, node);
          // node.parentText = parent? parent.name: null;
          // node.parentId = parent_id
          node.text = name;
          node.is_active = is_active;
          node.icon = "enabled";
          // console.log(`order`, order)
          node.order = parseInt(order);
          //   this.order(tree);
          this.setIconState("enabled");
          this.updateRetrievedAt();
          this.tree.sort((a, b) => a.order - b.order);
        } else {
          //   console.log(`res.resource`, res.resource);
          //   console.log(`this.parents`, this.parents)
          //   const { id, parent_id, name, order, is_active } = res.resource;
          //   const node = this.parents[id];
          //   console.log(`node`, node)
          //   const parent = this.parents[parent_id];
          // //   console.log(`parent`, parent);
          //   node.parentText = parent ? parent.name : null;
          //   node.parentId = parent_id;
          //   node.text = 'ABC';
          //   node.is_active = is_active;
          //   node.icon = parent
          //     ? this.isActiveIcon(is_active, parent.icon === "enabled")
          //     : null;
          //   console.log(`order`, order);
          //   node.order = parseInt(order);
          //   this.setIconState(node.children, node.icon === "enabled");
          //   this.updateRetrievedAt();
          //   this.order(parent.children);
        //   console.log(`res.resource`, res.resource);
          const { id, parent_id, name, order, is_active } = res.resource;
          const node = this.parents[id];
          const node2Parent = this.tree.find((tr) => tr.id === parent_id);
        //   console.log(`node2Parent`, typeof node2Parent, node2Parent);
          for (const [key, value] of Object.entries(node2Parent.children)) {
            if (value.id === id) {
            //   console.log(`value`, value);
              //   Vue.delete(this.tree, key);
              value.order = parseInt(order);
            }
          }
          //   const node2 = node2Parent.forEach((no) => console.log(`no`, no));
        //   console.log(`node2Parent`, node2Parent);
          // const node2=
          const parent = this.parents[parent_id];
          node.parentText = parent.text;
          node.parentId = parent_id;
          node.text = name;
          node.is_active = is_active;
          node.icon = this.isActiveIcon(is_active, parent.icon === "enabled");
        //   console.log(`order`, order);
          node.order = parseInt(order);
        //   console.log(`this`, this);
          this.order(parent.children);
          this.setIconState(node.children, node.icon === "enabled");
          this.updateRetrievedAt();
        }
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
