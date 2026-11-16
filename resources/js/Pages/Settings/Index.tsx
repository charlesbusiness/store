import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { useAuthStore } from '@/stores/authStore';
import { toast } from 'sonner';

interface BranchRow {
    id: number;
    name: string;
    code: string;
    is_default: boolean;
    is_active: boolean;
}

interface MemberRow {
    id: number;
    name: string;
    email: string;
    branch_ids?: number[];
    pivot?: { role?: string };
}

const roleLabel = (role?: string) => role === 'owner' ? 'Owner' : role === 'admin' ? 'Admin' : 'Member';

export default function Settings() {
    const queryClient = useQueryClient();
    const { activeTenantId, refreshMemberships } = useAuthStore();
    const [branchName, setBranchName] = useState('');
    const [branchCode, setBranchCode] = useState('');
    const [memberName, setMemberName] = useState('');
    const [memberEmail, setMemberEmail] = useState('');
    const [memberRole, setMemberRole] = useState('member');
    const [memberBranchIds, setMemberBranchIds] = useState<number[]>([]);
    const [branchDrafts, setBranchDrafts] = useState<Record<number, number[]>>({});

    const tenantQuery = useQuery({
        queryKey: ['tenant', activeTenantId],
        queryFn: () => api.get('/tenant'),
        enabled: Boolean(activeTenantId),
    });
    const membersQuery = useQuery({
        queryKey: ['tenant-members', activeTenantId],
        queryFn: () => api.get('/tenant/members'),
        enabled: Boolean(activeTenantId),
    });

    const tenant = tenantQuery.data?.data?.tenant;
    const branches: BranchRow[] = tenantQuery.data?.data?.branches ?? [];
    const members: MemberRow[] = membersQuery.data?.data ?? [];
    const role = tenantQuery.data?.data?.role;
    const canManage = role === 'owner' || role === 'admin';
    const canManageUsers = role === 'owner';
    const currentUserId = useAuthStore.getState().user?.id;

    const updateTenant = useMutation({
        mutationFn: (data: Record<string, string>) => api.patch('/tenant', data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['tenant'] });
            toast.success('Settings updated.');
        },
        onError: () => toast.error('Unable to update settings.'),
    });

    const createBranch = useMutation({
        mutationFn: () => api.post('/branches', { name: branchName, code: branchCode }),
        onSuccess: async () => {
            setBranchName('');
            setBranchCode('');
            await refreshMemberships().catch(() => undefined);
            queryClient.invalidateQueries({ queryKey: ['tenant'] });
            queryClient.invalidateQueries({ queryKey: ['branches'] });
            toast.success('Branch created.');
        },
        onError: (error: any) => toast.error(error.response?.data?.message ?? 'Unable to create branch.'),
    });

    const disableBranch = useMutation({
        mutationFn: (id: number) => api.delete(`/branches/${id}`),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['tenant'] });
            queryClient.invalidateQueries({ queryKey: ['branches'] });
            refreshMemberships().catch(() => undefined);
            toast.success('Branch disabled.');
        },
        onError: (error: any) => toast.error(error.response?.data?.message ?? 'Unable to disable branch.'),
    });

    const updateMember = useMutation({
        mutationFn: ({ id, role }: { id: number; role: string }) => api.patch(`/tenant/members/${id}`, { role }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['tenant-members'] });
            toast.success('Member updated.');
        },
        onError: (error: any) => toast.error(error.response?.data?.message ?? 'Unable to update member.'),
    });

    const createMember = useMutation({
        mutationFn: () => api.post('/tenant/members', {
            name: memberName,
            email: memberEmail,
            role: canManageUsers ? memberRole : 'member',
            branch_ids: memberBranchIds,
        }),
        onSuccess: () => {
            setMemberName('');
            setMemberEmail('');
            setMemberRole('member');
            setMemberBranchIds([]);
            queryClient.invalidateQueries({ queryKey: ['tenant-members'] });
            toast.success('Invite created.');
        },
        onError: (error: any) => toast.error(error.response?.data?.message ?? 'Unable to add member.'),
    });

    const updateMemberBranches = useMutation({
        mutationFn: ({ id, branchIds }: { id: number; branchIds: number[] }) => api.put(`/tenant/members/${id}/branches`, { branch_ids: branchIds }),
        onSuccess: (_response, variables) => {
            setBranchDrafts((current) => {
                const next = { ...current };
                delete next[variables.id];
                return next;
            });
            queryClient.invalidateQueries({ queryKey: ['tenant-members'] });
            toast.success('Branch access updated.');
        },
        onError: (error: any) => toast.error(error.response?.data?.message ?? 'Unable to update branches.'),
    });

    const resendInvite = useMutation({
        mutationFn: (id: number) => api.post(`/tenant/members/${id}/invite`),
        onSuccess: () => toast.success('Invite sent.'),
        onError: (error: any) => toast.error(error.response?.data?.message ?? 'Unable to send invite.'),
    });

    const draftFor = (member: MemberRow): number[] => branchDrafts[member.id] ?? member.branch_ids ?? [];

    const toggleMemberBranch = (member: MemberRow, branchId: number, checked: boolean) => {
        const current = draftFor(member);
        const next = checked ? [...current, branchId] : current.filter((id) => id !== branchId);
        setBranchDrafts((drafts) => ({ ...drafts, [member.id]: next }));
    };

    const toggleNewMemberBranch = (branchId: number, checked: boolean) => {
        setMemberBranchIds((current) => checked ? [...current, branchId] : current.filter((id) => id !== branchId));
    };

    if (tenantQuery.isLoading) return <div className="card">Loading settings...</div>;

    return (
        <div className="p-6">
            <div className="card-head">
                <h1>Account settings</h1>
                <span className="mut">Role: {role}</span>
            </div>

            <section className="card">
                <h2>Account</h2>
                <div className="g">
                    <label>
                        Account name
                        <input defaultValue={tenant?.name ?? ''} disabled={!canManage} onBlur={(event) => {
                            if (event.target.value !== tenant?.name) updateTenant.mutate({ name: event.target.value });
                        }} />
                    </label>
                    <label>
                        Stock management
                        <select defaultValue={tenant?.stock_management_mode ?? 'account'} disabled={!canManage} onChange={(event) => updateTenant.mutate({ stock_management_mode: event.target.value })}>
                            <option value="account">Account-wide stock</option>
                            <option value="branch">Separate branch stock</option>
                        </select>
                    </label>
                    <label>
                        Report view
                        <select defaultValue={tenant?.reporting_mode ?? 'account'} disabled={!canManage} onChange={(event) => updateTenant.mutate({ reporting_mode: event.target.value })}>
                            <option value="account">Account total</option>
                            <option value="branch">Branch view</option>
                        </select>
                    </label>
                </div>
            </section>

            <section className="card">
                <h2>Branches</h2>
                {canManage && (
                    <div className="g">
                        <label>Branch name<input value={branchName} onChange={(event) => setBranchName(event.target.value)} placeholder="Branch name" /></label>
                        <label>Branch code<input value={branchCode} onChange={(event) => setBranchCode(event.target.value)} placeholder="branch-code" /></label>
                        <div className="row"><button className="btn" disabled={!branchName || !branchCode || createBranch.isPending} onClick={() => createBranch.mutate()}>Add branch</button></div>
                    </div>
                )}
                <div className="sc">
                    <table>
                        <thead><tr><th>Name</th><th>Code</th><th>Status</th><th /></tr></thead>
                        <tbody>{branches.map((branch) => <tr key={branch.id}><td>{branch.name}</td><td>{branch.code}</td><td>{branch.is_active ? 'Active' : 'Disabled'}</td><td className="r">{canManage && branch.is_active && !branch.is_default && <button className="btn alt sm" onClick={() => disableBranch.mutate(branch.id)}>Disable</button>}</td></tr>)}</tbody>
                    </table>
                </div>
            </section>

            {canManage && <section className="card">
                <h2>Members</h2>

                <div className="g">
                    <label>Name<input value={memberName} onChange={(event) => setMemberName(event.target.value)} placeholder="Full name" /></label>
                    <label>Email<input type="email" value={memberEmail} onChange={(event) => setMemberEmail(event.target.value)} placeholder="name@example.com" /></label>
                    <label>Role
                        <select value={canManageUsers ? memberRole : 'member'} disabled={!canManageUsers} onChange={(event) => setMemberRole(event.target.value)}>
                            <option value="member">Member</option>
                            <option value="admin">Admin</option>
                        </select>
                    </label>
                </div>

                <div className="mb-3">
                    <div className="mut mb-1">Branch access</div>
                    <div className="flex flex-wrap gap-3">
                        {branches.filter((branch) => branch.is_active).map((branch) => (
                            <label key={branch.id} className="inline-flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={memberBranchIds.includes(branch.id)}
                                    onChange={(event) => toggleNewMemberBranch(branch.id, event.target.checked)}
                                />
                                {branch.name}
                            </label>
                        ))}
                    </div>
                </div>

                <div className="row">
                    <button
                        className="btn"
                        disabled={!memberName || !memberEmail || memberBranchIds.length === 0 || createMember.isPending}
                        onClick={() => createMember.mutate()}
                    >
                        Add member
                    </button>
                </div>

                <div className="sc mt-4">
                    <table>
                        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Branches</th><th /></tr></thead>
                        <tbody>
                            {members.map((member) => {
                                const draft = draftFor(member);
                                const saved = member.branch_ids ?? [];
                                const changed = draft.length !== saved.length || draft.some((id) => !saved.includes(id));

                                return (
                                    <tr key={member.id}>
                                        <td>{member.name}</td>
                                        <td>{member.email}</td>
                                        <td>
                                            {canManageUsers && member.id !== currentUserId ? (
                                                <select value={member.pivot?.role ?? 'member'} onChange={(event) => updateMember.mutate({ id: member.id, role: event.target.value })}>
                                                    <option value="admin">Admin</option>
                                                    <option value="member">Member</option>
                                                </select>
                                            ) : roleLabel(member.pivot?.role)}
                                        </td>
                                        <td>
                                            <div className="flex flex-wrap gap-2">
                                                {branches.filter((branch) => branch.is_active).map((branch) => (
                                                    <label key={branch.id} className="inline-flex items-center gap-1 text-sm">
                                                        <input
                                                            type="checkbox"
                                                            checked={draft.includes(branch.id)}
                                                            onChange={(event) => toggleMemberBranch(member, branch.id, event.target.checked)}
                                                        />
                                                        {branch.name}
                                                    </label>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="r">
                                            <div className="flex justify-end gap-2">
                                                {changed && (
                                                    <button
                                                        className="btn sm"
                                                        disabled={draft.length === 0 || updateMemberBranches.isPending}
                                                        onClick={() => updateMemberBranches.mutate({ id: member.id, branchIds: draft })}
                                                    >
                                                        Save branches
                                                    </button>
                                                )}
                                                {member.id !== currentUserId && (
                                                    <button
                                                        className="btn alt sm"
                                                        disabled={resendInvite.isPending}
                                                        onClick={() => resendInvite.mutate(member.id)}
                                                    >
                                                        Resend invite
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </section>}
        </div>
    );
}
