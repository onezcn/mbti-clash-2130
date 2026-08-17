import path from 'node:path';
import fs from 'node:fs';
import Datastore from '@seald-io/nedb';

// 简单回调 -> Promise 包装（保持 MongoDB 查询语法原样）
const call = (store, method, ...args) =>
    new Promise((resolve, reject) => {
        store[method](...args, (err, result) => (err ? reject(err) : resolve(result)));
    });

const hasOperators = (doc) =>
    doc && typeof doc === 'object' &&
    Object.keys(doc).some((k) => k.startsWith('$'));

/**
 * onezRemoteDB —— 内嵌文件数据库，MongoDB 风格 API（@seald-io/nedb 驱动）
 * 用法：
 *   const db = new onezRemoteDB('data', './data');
 *   await db.insert('players', { id: 1, name: '玩家A', score: 100 });
 *   await db.one('players', { id: 1 });
 *   await db.rows('players', { score: { $gt: 50 } });              // 返回数量(数字)
 *   await db.update('players', { score: 200 }, { id: 1 });
 *   await db.delete('players', { id: 2 });
 *   await db.record('players', {}, { sort: { id: -1 }, limit: 10, skip: 10 });   // 分页获取
 */
export class onezRemoteDB {
    constructor(dbName = 'data', dataDir = process.cwd()) {
        this.dbName = dbName;
        this.dataDir = dataDir;
        this.stores = new Map(); // table -> Datastore
    }

    _store(table) {
        if (!this.stores.has(table)) {
            fs.mkdirSync(this.dataDir, { recursive: true });
            const file = `${this.dbName}.${String(table).replace(/[^\w.-]/g, '_')}.db`;
            const store = new Datastore({ filename: path.join(this.dataDir, file), autoload: true });
            this.stores.set(table, store);
        }
        return this.stores.get(table);
    }

    /** 插入一条（或数组多条）记录，返回插入后的文档（含 _id） */
    async insert(table, item) {
        return await call(this._store(table), 'insert', item);
    }

    /** 更新匹配 where 的记录：item 含 $ 操作符($set/$inc/$push…)则原样用，否则视为 $set 局部更新；返回更新条数 */
    async update(table, item, where = {}) {
        const patch = hasOperators(item) ? item : { $set: item };
        return await call(this._store(table), 'update', where, patch, { multi: true });
    }

    /** 删除匹配 where 的记录，返回删除条数 */
    async delete(table, where = {}) {
        return await call(this._store(table), 'remove', where, { multi: true });
    }

    /** 获取记录（列表）：按 where 查询，option 支持 { sort, limit, skip } 分页 */
    async record(table, where = {}, option = {}) {
        const { sort, limit, skip } = option || {};
        return await new Promise((resolve, reject) => {
            let cursor = this._store(table).find(where);
            if (sort) cursor = cursor.sort(sort);
            if (skip) cursor = cursor.skip(skip);
            if (limit) cursor = cursor.limit(limit);
            cursor.exec((err, docs) => (err ? reject(err) : resolve(docs)));
        });
    }

    /** 获取匹配 where 的记录数量，返回数字（支持 $gt/$in/$regex 等 MongoDB 语法） */
    async rows(table, where = {}) {
        return await call(this._store(table), 'count', where);
    }

    /** 查询匹配 where 的第一条；option.sort 可排序，option 也可作为投影 */
    async one(table, where = {}, option = {}) {
        if (option && option.sort) {
            return await new Promise((resolve, reject) => {
                this._store(table)
                    .find(where)
                    .sort(option.sort)
                    .limit(1)
                    .exec((err, docs) => (err ? reject(err) : resolve(docs[0] || null)));
            });
        }
        return await call(this._store(table), 'findOne', where, option);
    }
}

export default onezRemoteDB;
