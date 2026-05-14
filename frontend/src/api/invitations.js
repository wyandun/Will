import apiClient from './client';

export const invitationsApi = {
  /** List pending (not yet accepted) invitations. */
  getInvitations: () =>
    apiClient.get('/invitations').then((res) => ({
      data: res.data.data ?? [],
    })),

  /** Send a new invitation (or regenerate if email already has a pending one). */
  sendInvitation: (data) =>
    apiClient.post('/invitations', data).then((res) => res.data),

  /** Regenerate token and resend email for an existing pending invitation. */
  resendInvitation: (id) =>
    apiClient.post(`/invitations/${id}/resend`).then((res) => res.data),

  /** Revoke a pending invitation (soft-deletes the placeholder user). */
  revokeInvitation: (id) =>
    apiClient.delete(`/invitations/${id}`).then((res) => res.data),

  /**
   * PUBLIC — Verify that a token is valid and return safe user info
   * (name, email, role) for the activation form.
   */
  verifyInvitation: (token) =>
    apiClient.get(`/invitations/${token}/verify`).then((res) => res.data.data),

  /**
   * PUBLIC — Accept an invitation: set password, receive Sanctum token
   * for auto-login.
   */
  acceptInvitation: (token, data) =>
    apiClient.post(`/invitations/${token}/accept`, data).then((res) => res.data.data),
};
