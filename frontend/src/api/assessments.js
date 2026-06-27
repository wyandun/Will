import apiClient from './client';

export const assessmentsApi = {
  /**
   * Retrieve all assessment contacts (paginated).
   * Access: superadmin, admin_sm.
   */
  getContacts: () =>
    apiClient.get('/assessment-contacts').then((res) => ({
      data: res.data.data,
      meta: res.data.meta,
    })),

  /**
   * Retrieve a single assessment contact by ID.
   * @param {number} id
   */
  getContact: (id) =>
    apiClient.get(`/assessment-contacts/${id}`).then((res) => res.data.data),

  /**
   * Save an internal audit note on an assessment contact.
   * Only admin_sm and superadmin may call this.
   * @param {number} id - assessment contact ID
   * @param {string|null} adminNote - the note text (nullable, max 2000 chars)
   */
  saveAdminNote: (id, adminNote) =>
    apiClient
      .patch(`/assessment-contacts/${id}/admin-note`, { admin_note: adminNote })
      .then((res) => res.data.data),
};
