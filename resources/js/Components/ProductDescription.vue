<script setup>
import { computed } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    content: { type: String, default: '' },
    compact: { type: Boolean, default: false },
});

const headingAliases = new Map([
    ['key features', 'Key Features'],
    ['features', 'Features'],
    ['product highlights', 'Product Highlights'],
    ['technical specifications', 'Technical Specifications'],
    ['specifications', 'Specifications'],
    ['compatible devices', 'Compatible Devices'],
    ['compatibility', 'Compatibility'],
    ['package contents', 'Package Contents'],
    ["what's in the box", "What's in the Box"],
    ['what is in the box', "What's in the Box"],
    ['important information', 'Important Information'],
    ['important notes', 'Important Notes'],
    ['warranty', 'Warranty'],
    ['delivery information', 'Delivery Information'],
]);

const listSections = new Set([
    'key features', 'features', 'product highlights', 'compatible devices', 'compatibility',
    'package contents', "what's in the box", 'important information', 'important notes',
]);
const tableSections = new Set(['technical specifications', 'specifications']);

const sectionKey = (value = '') => value.trim().replace(/:$/, '').toLowerCase();
const slugify = (value = '') => value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'details';
const cleanInline = (value = '') => value.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1').replace(/\*\*([^*]+)\*\*/g, '$1').replace(/__([^_]+)__/g, '$1').replace(/`([^`]+)`/g, '$1').trim();
const headingFromLine = (line) => { const markdown = line.match(/^#{1,4}\s+(.+)$/); if (markdown) return cleanInline(markdown[1]); const normalized = sectionKey(line); return headingAliases.get(normalized) || null; };
const tableCells = (line, tableMode = false) => {
    const trimmed = line.trim(); if (!trimmed) return null; if (/^\|?\s*:?-{3,}:?\s*\|/.test(trimmed)) return [];
    if (trimmed.includes('|')) { const cells = trimmed.replace(/^\|/, '').replace(/\|$/, '').split('|').map(cleanInline); return cells.length >= 2 ? cells : null; }
    if (trimmed.includes('\t')) { const cells = trimmed.split(/\t+/).map(cleanInline).filter(Boolean); return cells.length >= 2 ? [cells[0], cells.slice(1).join(' ')] : null; }
    if (tableMode && /^specification\s+details$/i.test(trimmed)) return ['Specification', 'Details'];
    if (tableMode) { const cells = trimmed.split(/\s{2,}/).map(cleanInline).filter(Boolean); if (cells.length >= 2) return [cells[0], cells.slice(1).join(' ')]; }
    return null;
};

const blocks = computed(() => {
    const lines = String(props.content || '').replace(/\r\n?/g, '\n').split('\n');
    const output = []; let paragraph = []; let list = []; let table = []; let section = ''; let hasHeading = false; const ids = new Map();
    const uniqueId = (title) => { const base = slugify(title); const count = (ids.get(base) || 0) + 1; ids.set(base, count); return count === 1 ? base : `${base}-${count}`; };
    const flushParagraph = () => { if (!paragraph.length) return; output.push({ type: 'paragraph', text: cleanInline(paragraph.join(' ')), intro: !hasHeading, section }); paragraph = []; };
    const flushList = () => { if (!list.length) return; output.push({ type: 'list', items: list.map(cleanInline).filter(Boolean), section }); list = []; };
    const flushTable = () => { if (!table.length) return; const rows = table.filter((row) => row.length >= 2); if (rows.length) { const first = rows[0]; const looksLikeHeader = /specification|feature|attribute|item/i.test(first[0]) && /detail|value|description/i.test(first[1]); output.push({ type: 'table', headers: looksLikeHeader ? first : ['Specification', 'Details'], rows: looksLikeHeader ? rows.slice(1) : rows, section }); } table = []; };
    const flushAll = () => { flushParagraph(); flushList(); flushTable(); };
    for (const rawLine of lines) {
        const line = rawLine.trim(); const heading = headingFromLine(line);
        if (heading) { flushAll(); section = heading; hasHeading = true; output.push({ type: 'heading', text: heading, id: uniqueId(heading) }); continue; }
        if (!line) { flushAll(); continue; }
        const currentKey = sectionKey(section); const bullet = line.match(/^\s*(?:[-*+•✓✔]|\d+[.)])\s+(.+)$/);
        if (bullet) { flushParagraph(); flushTable(); list.push(bullet[1]); continue; }
        const cells = tableCells(line, tableSections.has(currentKey));
        if (cells !== null && tableSections.has(currentKey)) { flushParagraph(); flushList(); if (cells.length) table.push(cells); continue; }
        if (listSections.has(currentKey)) { flushParagraph(); flushTable(); list.push(line); continue; }
        paragraph.push(line);
    }
    flushAll(); return output.filter((block) => block.type !== 'paragraph' || block.text);
});

const headings = computed(() => blocks.value.filter((block) => block.type === 'heading'));
const isFeatureGrid = (section = '') => ['key features', 'features', 'product highlights', 'compatible devices', 'compatibility'].includes(sectionKey(section));
</script>

<template>
    <div class="product-description">
        <nav v-if="!compact && headings.length" class="mb-8 flex gap-2 overflow-x-auto pb-1 scrollbar-hidden" aria-label="Product description sections"><a v-for="heading in headings" :key="heading.id" :href="`#${heading.id}`" class="shrink-0 rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs font-extrabold text-slate-600 transition hover:border-prinx-300 hover:text-prinx-600">{{ heading.text }}</a></nav>
        <div :class="compact ? 'space-y-5' : 'space-y-7'">
            <template v-for="(block, index) in blocks" :key="`${block.type}-${index}`">
                <h3 v-if="block.type === 'heading'" :id="block.id" :class="['scroll-mt-28 border-t border-slate-200 pt-7 font-black tracking-tight text-slate-950', compact ? 'text-lg' : 'text-2xl']">{{ block.text }}</h3>
                <p v-else-if="block.type === 'paragraph'" :class="['text-slate-600', compact ? 'text-sm leading-7' : 'text-base leading-8', block.intro && !compact ? 'first:text-lg first:font-medium first:leading-8 first:text-slate-700' : '']">{{ block.text }}</p>
                <ul v-else-if="block.type === 'list'" :class="['grid gap-3', isFeatureGrid(block.section) && !compact ? 'sm:grid-cols-2' : 'grid-cols-1']"><li v-for="item in block.items" :key="item" :class="['flex items-start gap-3 rounded-[8px] border border-slate-200 bg-slate-50/70', compact ? 'p-3 text-sm' : 'p-4 text-sm leading-6']"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-[6px] bg-prinx-100 text-prinx-700"><Icon name="tick-circle" :size="14" /></span><span class="text-slate-700">{{ item }}</span></li></ul>
                <div v-else-if="block.type === 'table'" class="overflow-hidden rounded-[8px] border border-slate-200 bg-white"><div class="overflow-x-auto scrollbar-hidden"><table class="min-w-[620px] w-full border-collapse text-left text-sm"><thead><tr class="bg-slate-950 text-white"><th v-for="header in block.headers" :key="header" class="px-4 py-3 font-extrabold">{{ header }}</th></tr></thead><tbody class="divide-y divide-slate-200"><tr v-for="(row, rowIndex) in block.rows" :key="rowIndex" class="odd:bg-white even:bg-slate-50/70"><td v-for="(cell, cellIndex) in row" :key="cellIndex" :class="['px-4 py-3 align-top text-slate-700', cellIndex === 0 ? 'w-[38%] font-bold text-slate-900' : '']">{{ cell }}</td></tr></tbody></table></div></div>
            </template>
        </div>
    </div>
</template>
