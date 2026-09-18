<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    scheduler: Object,
    queue: Object,
    queueConnection: String,
    counts: Object,
    serverTime: String,
    launchChecks: { type: Array, default: () => [] },
    commands: Object,
});

const refresh = () => router.reload({ only: ['scheduler', 'queue', 'counts', 'serverTime', 'launchChecks'] });

const statusClass = (running) => running
    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
    : 'border-red-200 bg-red-50 text-red-700';

const formatSeen = (value) => value ? new Date(value).toLocaleString() : 'Never';
const formatAge = (seconds) => seconds === null || seconds === undefined
    ? 'No heartbeat yet'
    : seconds < 60
        ? `${seconds}s ago`
        : `${Math.floor(seconds / 60)}m ${seconds % 60}s ago`;
</script>

<template>
    <AdminLayout>
        <Head title="System Monitoring" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="section-kicker">Infrastructure</p>
                <h1 class="section-title">System Monitoring</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">Monitor Laravel scheduler activity, queue processing and job backlog from the admin panel.</p>
            </div>
            <button class="btn-light" @click="refresh"><Icon name="refresh-2" :size="17" /> Refresh status</button>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <section :class="['rounded-[10px] border p-5', statusClass(scheduler.running)]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-[.12em] opacity-70">Scheduler / Cron</p>
                        <h2 class="mt-2 text-xl font-semibold">{{ scheduler.running ? 'Running' : 'Not detected' }}</h2>
                    </div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-[9px] bg-white/70"><Icon :name="scheduler.running ? 'timer-start' : 'timer-pause'" :size="21" /></div>
                </div>
                <p class="mt-4 text-sm">{{ formatAge(scheduler.age_seconds) }}</p>
            </section>

            <section :class="['rounded-[10px] border p-5', statusClass(queue.running)]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-[.12em] opacity-70">Queue worker</p>
                        <h2 class="mt-2 text-xl font-semibold">{{ queue.running ? 'Running' : 'Not detected' }}</h2>
                    </div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-[9px] bg-white/70"><Icon :name="queue.running ? 'cpu-charge' : 'danger'" :size="21" /></div>
                </div>
                <p class="mt-4 text-sm">{{ formatAge(queue.age_seconds) }}</p>
            </section>

            <section class="card p-5">
                <p class="text-xs font-medium uppercase tracking-[.12em] text-slate-400">Pending jobs</p>
                <div class="mt-3 flex items-end justify-between gap-3">
                    <strong class="text-3xl font-semibold text-slate-950">{{ counts.pending ?? '—' }}</strong>
                    <Icon name="task-square" :size="23" class="text-amber-500" />
                </div>
                <p class="mt-3 text-xs text-slate-500">Current jobs waiting in the queue.</p>
            </section>

            <section class="card p-5">
                <p class="text-xs font-medium uppercase tracking-[.12em] text-slate-400">Failed jobs</p>
                <div class="mt-3 flex items-end justify-between gap-3">
                    <strong class="text-3xl font-semibold" :class="counts.failed ? 'text-red-600' : 'text-slate-950'">{{ counts.failed ?? '—' }}</strong>
                    <Icon name="close-square" :size="23" class="text-red-500" />
                </div>
                <p class="mt-3 text-xs text-slate-500">Jobs recorded in Laravel's failed queue table.</p>
            </section>
        </div>

        <section class="card mt-6 overflow-hidden">
            <div class="border-b border-slate-200 p-5">
                <h2 class="font-semibold text-slate-900">Launch readiness</h2>
                <p class="mt-1 text-sm text-slate-500">Quick checks for the public sales and discovery stack. These checks do not invent traffic or sales data.</p>
            </div>
            <div class="divide-y divide-slate-100">
                <div v-for="check in launchChecks" :key="check.key" class="flex items-start justify-between gap-4 p-4">
                    <div><p class="text-sm font-semibold text-slate-900">{{ check.label }}</p><p class="mt-1 text-xs leading-5 text-slate-500">{{ check.detail }}</p></div>
                    <span :class="['shrink-0 rounded-[6px] px-2.5 py-1 text-[10px] font-semibold', check.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700']">{{ check.ok ? 'Ready' : 'Needs attention' }}</span>
                </div>
            </div>
        </section>

        <div class="mt-6 grid gap-5 xl:grid-cols-[1fr_380px]">
            <section class="card overflow-hidden">
                <div class="border-b border-slate-200 p-5">
                    <h2 class="font-semibold text-slate-900">Heartbeat details</h2>
                    <p class="mt-1 text-sm text-slate-500">A service is considered healthy when its heartbeat was seen within the last 3 minutes.</p>
                </div>
                <div class="grid gap-0 sm:grid-cols-2">
                    <div class="border-b border-slate-200 p-5 sm:border-b-0 sm:border-r">
                        <div class="flex items-center gap-3"><Icon name="calendar-tick" :size="20" class="text-prinx-500" /><h3 class="font-semibold">Scheduler heartbeat</h3></div>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Status</dt><dd :class="scheduler.running ? 'font-medium text-emerald-600' : 'font-medium text-red-600'">{{ scheduler.running ? 'Healthy' : 'Missing' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Last seen</dt><dd class="text-right font-medium text-slate-800">{{ formatSeen(scheduler.last_seen) }}</dd></div>
                        </dl>
                    </div>
                    <div class="p-5">
                        <div class="flex items-center gap-3"><Icon name="cpu-setting" :size="20" class="text-prinx-500" /><h3 class="font-semibold">Queue heartbeat</h3></div>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Status</dt><dd :class="queue.running ? 'font-medium text-emerald-600' : 'font-medium text-red-600'">{{ queue.running ? 'Healthy' : 'Missing' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Last processed</dt><dd class="text-right font-medium text-slate-800">{{ formatSeen(queue.last_seen) }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Connection</dt><dd class="font-medium text-slate-800">{{ queueConnection }}</dd></div>
                        </dl>
                    </div>
                </div>
            </section>

            <aside class="space-y-5">
                <section class="rounded-[10px] bg-slate-950 p-5 text-white">
                    <div class="flex items-center gap-3"><Icon name="code-1" :size="20" class="text-prinx-400" /><h2 class="font-semibold">Required cron command</h2></div>
                    <p class="mt-2 text-sm leading-6 text-slate-300">On cPanel, run Laravel's scheduler every minute.</p>
                    <code class="mt-4 block overflow-x-auto rounded-[8px] bg-black/40 p-3 text-xs leading-5 text-prinx-300">{{ commands.cron }}</code>
                </section>

                <section class="card p-5">
                    <div class="flex items-center gap-3"><Icon name="setting-3" :size="20" class="text-prinx-500" /><h2 class="font-semibold">Queue worker command</h2></div>
                    <p class="mt-2 text-sm leading-6 text-slate-500">A queue worker must stay alive separately from the scheduler.</p>
                    <code class="mt-4 block overflow-x-auto rounded-[8px] bg-slate-950 p-3 text-xs leading-5 text-prinx-300">{{ commands.queue }}</code>
                </section>

                <section class="card p-5">
                    <p class="text-xs font-medium uppercase tracking-[.12em] text-slate-400">Server time</p>
                    <p class="mt-2 font-medium text-slate-800">{{ formatSeen(serverTime) }}</p>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
