import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { systemAdminsApi } from '../../api/systemAdmins';
import SystemAdminFormModal from './SystemAdminFormModal';

export default function SystemAdminsPage() {
  const { t } = useTranslation('common');

  const [admins, setAdmins] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [fetchError, setFetchError] = useState('');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingAdmin, setEditingAdmin] = useState(null);

  const loadAdmins = useCallback(async () => {
    setIsLoading(true);
    setFetchError('');
    try {
      const { data } = await systemAdminsApi.getSystemAdmins();
      setAdmins(Array.isArray(data) ? data : []);
    } catch (error) {
      setFetchError(error?.response?.data?.message ?? t('system_admins.load_error'));
    } finally {
      setIsLoading(false);
    }
  }, [t]);

  useEffect(() => {
    loadAdmins();
  }, [loadAdmins]);

  function openCreateModal() {
    setEditingAdmin(null);
    setIsModalOpen(true);
  }

  function openEditModal(admin) {
    setEditingAdmin(admin);
    setIsModalOpen(true);
  }

  function closeModal() {
    setIsModalOpen(false);
    setEditingAdmin(null);
  }

  async function handleSave(payload, id) {
    if (id) {
      await systemAdminsApi.updateSystemAdmin(id, payload);
    } else {
      await systemAdminsApi.createSystemAdmin(payload);
    }
    closeModal();
    await loadAdmins();
  }

  async function handleDelete(admin) {
    if (!window.confirm(t('system_admins.delete_confirm', { name: admin.name }))) return;

    try {
      await systemAdminsApi.deleteSystemAdmin(admin.id);
      await loadAdmins();
    } catch (error) {
      window.alert(error?.response?.data?.message ? t(error.response.data.message) : t('common.unexpected_error'));
    }
  }

  return (
    <>
      <div className="space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-semibold text-slate-800">{t('system_admins.title')}</h1>
            <p className="mt-0.5 text-sm text-slate-500">
              {t('system_admins.subtitle')}
            </p>
          </div>
          <button
            onClick={openCreateModal}
            className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            {t('system_admins.new')}
          </button>
        </div>

        {/* Loading */}
        {isLoading && (
          <div className="flex items-center justify-center py-20 gap-3">
            <svg className="w-6 h-6 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p className="text-sm text-slate-500">{t('common.loading')}</p>
          </div>
        )}

        {/* Fetch error */}
        {!isLoading && fetchError && (
          <div className="rounded-xl bg-red-50 border border-red-200 px-5 py-4 flex items-start gap-3">
            <svg className="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <div>
              <p className="text-sm font-medium text-red-700">{fetchError}</p>
              <button
                onClick={loadAdmins}
                className="mt-1 text-xs text-red-600 underline hover:text-red-800"
              >
                {t('common.try_again')}
              </button>
            </div>
          </div>
        )}

        {/* List */}
        {!isLoading && !fetchError && admins.length === 0 && (
          <div className="flex flex-col items-center justify-center py-20 text-center">
             <p className="text-sm text-slate-500">{t('system_admins.empty')}</p>
          </div>
        )}

        {!isLoading && !fetchError && admins.length > 0 && (
          <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <ul className="divide-y divide-slate-100">
              {admins.map(admin => (
                <li key={admin.id} className="p-4 flex flex-col sm:flex-row sm:items-center justify-between hover:bg-slate-50 transition-colors gap-4">
                  <div>
                    <h3 className="font-semibold text-slate-800 flex items-center gap-2">
                      {admin.name}
                      <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium border ${admin.roles?.[0]?.name === 'system_admin' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-slate-100 text-slate-700 border-slate-200'}`}>
                        {admin.roles?.map(r => t(`roles.${r.name}`)).join(', ')}
                      </span>
                    </h3>
                    <p className="text-sm text-slate-500 mt-1">{admin.email}</p>
                  </div>
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => openEditModal(admin)}
                      className="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                      title={t('common.edit')}
                    >
                      <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                      </svg>
                    </button>
                    <button
                      onClick={() => handleDelete(admin)}
                      className="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                      title={t('common.delete')}
                    >
                      <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                      </svg>
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>

      {isModalOpen && (
        <SystemAdminFormModal
          initialData={editingAdmin}
          onClose={closeModal}
          onSave={handleSave}
        />
      )}
    </>
  );
}
