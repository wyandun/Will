import { useState, useEffect, useRef, useCallback } from 'react';
import PropTypes from 'prop-types';
import Cropper from 'react-easy-crop';
import { useAuthStore } from '../../store/authStore';
import { profileApi } from '../../api/profile';

// ─── Constants ────────────────────────────────────────────────────────────────

const ROLE_BADGE = {
  superadmin:           { label: 'Superadmin',             classes: 'bg-red-100 text-red-700' },
  admin_sm:             { label: 'Admin SM',               classes: 'bg-orange-100 text-orange-700' },
  sb_owner:             { label: 'SB Owner',               classes: 'bg-blue-100 text-blue-700' },
  sb_employee:          { label: 'SB Employee',            classes: 'bg-slate-100 text-slate-600' },
  bb:                   { label: 'Business Bishop',        classes: 'bg-purple-100 text-purple-700' },
  sub_franchise_owner:  { label: 'Sub-Franchise Owner',   classes: 'bg-green-100 text-green-700' },
  sub_franchise_admin:  { label: 'Sub-Franchise Admin',   classes: 'bg-teal-100 text-teal-700' },
};

// ─── Crop helper ──────────────────────────────────────────────────────────────

async function getCroppedImg(imageSrc, pixelCrop) {
  const image = await createImageBitmap(await fetch(imageSrc).then(r => r.blob()));
  const canvas = document.createElement('canvas');
  const size = Math.min(pixelCrop.width, pixelCrop.height);
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(image, pixelCrop.x, pixelCrop.y, pixelCrop.width, pixelCrop.height, 0, 0, size, size);
  return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));
}

// ─── Avatar Crop Modal ────────────────────────────────────────────────────────

function AvatarCropModal({ imageSrc, onConfirm, onCancel, saving }) {
  const [crop, setCrop] = useState({ x: 0, y: 0 });
  const [zoom, setZoom] = useState(1);
  const [croppedAreaPixels, setCroppedAreaPixels] = useState(null);

  function handleCropComplete(_, pixels) {
    setCroppedAreaPixels(pixels);
  }

  async function handleConfirm() {
    if (!croppedAreaPixels) return;
    const blob = await getCroppedImg(imageSrc, croppedAreaPixels);
    onConfirm(blob);
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        {/* Header */}
        <div className="px-6 py-4 border-b border-slate-200">
          <h3 className="text-base font-semibold text-slate-800">Adjust your photo</h3>
          <p className="text-xs text-slate-500 mt-0.5">Drag to reposition. Use the slider to zoom.</p>
        </div>

        {/* Crop area */}
        <div className="relative w-full bg-slate-900" style={{ height: 380 }}>
          <Cropper
            image={imageSrc}
            crop={crop}
            zoom={zoom}
            aspect={1}
            cropShape="round"
            showGrid={false}
            onCropChange={setCrop}
            onZoomChange={setZoom}
            onCropComplete={handleCropComplete}
          />
        </div>

        {/* Zoom slider */}
        <div className="px-6 py-4 flex items-center gap-3">
          <svg className="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
          </svg>
          <input
            type="range"
            min={0.5}
            max={3}
            step={0.05}
            value={zoom}
            onChange={e => setZoom(Number(e.target.value))}
            className="flex-1 accent-blue-600"
            aria-label="Zoom"
          />
          <svg className="w-5 h-5 text-slate-400 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0zM11 8v6M8 11h6" />
          </svg>
        </div>

        {/* Actions */}
        <div className="px-6 pb-5 flex justify-end gap-3">
          <button
            type="button"
            disabled={saving}
            onClick={onCancel}
            className="px-5 py-2 rounded-lg text-sm font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 disabled:opacity-60 transition-colors"
          >
            Cancel
          </button>
          <button
            type="button"
            disabled={saving}
            onClick={handleConfirm}
            className="inline-flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-60 transition-colors"
          >
            {saving && <Spinner />}
            {saving ? 'Saving...' : 'Crop & Save'}
          </button>
        </div>
      </div>
    </div>
  );
}

AvatarCropModal.propTypes = {
  imageSrc: PropTypes.string.isRequired,
  onConfirm: PropTypes.func.isRequired,
  onCancel: PropTypes.func.isRequired,
  saving: PropTypes.bool,
};

// ─── Spinner ──────────────────────────────────────────────────────────────────

function Spinner() {
  return (
    <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
    </svg>
  );
}

// ─── Avatar ───────────────────────────────────────────────────────────────────

function Avatar({ name, avatarUrl, onFileSelect, uploading }) {
  const fileRef = useRef(null);

  const initials = (name || '')
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map(w => w[0]?.toUpperCase() ?? '')
    .join('');

  function handleChange(e) {
    const file = e.target.files?.[0];
    if (file) onFileSelect(file);
    e.target.value = '';
  }

  return (
    <div className="relative w-20 h-20 shrink-0">
      {avatarUrl ? (
        <img
          src={avatarUrl}
          alt={name}
          className="w-20 h-20 rounded-full object-cover border-2 border-slate-200"
        />
      ) : (
        <div className="w-20 h-20 rounded-full bg-blue-600 flex items-center justify-center border-2 border-slate-200">
          <span className="text-white text-2xl font-bold select-none">{initials || '?'}</span>
        </div>
      )}

      <button
        type="button"
        disabled={uploading}
        onClick={() => fileRef.current?.click()}
        className="absolute bottom-0 right-0 w-7 h-7 rounded-full bg-white border border-slate-200 shadow flex items-center justify-center hover:bg-slate-50 transition-colors disabled:opacity-60"
        title="Change avatar"
      >
        {uploading ? (
          <Spinner />
        ) : (
          <svg className="w-3.5 h-3.5 text-slate-600" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
          </svg>
        )}
      </button>

      <input
        ref={fileRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={handleChange}
      />
    </div>
  );
}

Avatar.propTypes = {
  name: PropTypes.string,
  avatarUrl: PropTypes.string,
  onFileSelect: PropTypes.func.isRequired,
  uploading: PropTypes.bool,
};

// ─── Field ────────────────────────────────────────────────────────────────────

function Field({ label, children, fullWidth }) {
  return (
    <div className={fullWidth ? 'col-span-2' : ''}>
      <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">
        {label}
      </label>
      {children}
    </div>
  );
}

Field.propTypes = {
  label: PropTypes.string.isRequired,
  children: PropTypes.node.isRequired,
  fullWidth: PropTypes.bool,
};

const inputClass =
  'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition';

// ─── Personal Info Tab ────────────────────────────────────────────────────────

function PersonalInfoTab({ profile, onProfileSaved }) {
  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    job_title: '',
    birth_date: '',
    bio: '',
  });
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const successTimer = useRef(null);
  const updateUser = useAuthStore((s) => s.updateUser);

  useEffect(() => {
    if (profile) {
      setForm({
        name:       profile.name       ?? '',
        email:      profile.email      ?? '',
        phone:      profile.phone      ?? '',
        job_title:  profile.job_title  ?? '',
        birth_date: profile.birth_date ?? '',
        bio:        profile.bio        ?? '',
      });
    }
  }, [profile]);

  useEffect(() => () => clearTimeout(successTimer.current), []);

  function handleChange(e) {
    setForm(prev => ({ ...prev, [e.target.name]: e.target.value }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');
    setSuccess('');
    setSubmitting(true);
    try {
      const updated = await profileApi.updateProfile(form);
      updateUser({ name: updated.name, email: updated.email, avatar_url: updated.avatar_url });
      onProfileSaved(updated);
      setSuccess('Profile updated successfully.');
      successTimer.current = setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err?.response?.data?.message ?? 'An error occurred. Please try again.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <Field label="Full Name">
          <input
            name="name"
            type="text"
            value={form.name}
            onChange={handleChange}
            className={inputClass}
            placeholder="John Smith"
          />
        </Field>

        <Field label="Email">
          <input
            name="email"
            type="email"
            value={form.email}
            onChange={handleChange}
            className={inputClass}
            placeholder="you@example.com"
          />
        </Field>

        <Field label="Phone">
          <input
            name="phone"
            type="text"
            value={form.phone}
            onChange={handleChange}
            className={inputClass}
            placeholder="+1 (555) 000-0000"
          />
        </Field>

        <Field label="Position">
          <input
            name="job_title"
            type="text"
            value={form.job_title}
            onChange={handleChange}
            className={inputClass}
            placeholder="Operations Manager"
          />
        </Field>

        <Field label="Date of Birth">
          <input
            name="birth_date"
            type="date"
            value={form.birth_date}
            onChange={handleChange}
            className={inputClass}
          />
        </Field>

        <Field label="Bio" fullWidth>
          <textarea
            name="bio"
            value={form.bio}
            onChange={handleChange}
            rows={4}
            className={`${inputClass} resize-none`}
            placeholder="A short description about yourself..."
          />
        </Field>
      </div>

      {error && <p className="text-sm text-red-600">{error}</p>}
      {success && <p className="text-sm text-green-600">{success}</p>}

      <div>
        <button
          type="submit"
          disabled={submitting}
          className="inline-flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-60 transition-colors"
        >
          {submitting && <Spinner />}
          {submitting ? 'Saving...' : 'Save Changes'}
        </button>
      </div>
    </form>
  );
}

PersonalInfoTab.propTypes = {
  profile: PropTypes.shape({
    name: PropTypes.string,
    email: PropTypes.string,
    phone: PropTypes.string,
    job_title: PropTypes.string,
    birth_date: PropTypes.string,
    bio: PropTypes.string,
  }),
  onProfileSaved: PropTypes.func.isRequired,
};

// ─── Security Tab ─────────────────────────────────────────────────────────────

function SecurityTab() {
  const [form, setForm] = useState({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
  });
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const successTimer = useRef(null);

  useEffect(() => () => clearTimeout(successTimer.current), []);

  function handleChange(e) {
    setForm(prev => ({ ...prev, [e.target.name]: e.target.value }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');
    setSuccess('');

    if (form.new_password !== form.new_password_confirmation) {
      setError('New password and confirmation do not match.');
      return;
    }

    setSubmitting(true);
    try {
      await profileApi.updatePassword(form);
      setForm({ current_password: '', new_password: '', new_password_confirmation: '' });
      setSuccess('Password updated successfully.');
      successTimer.current = setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err?.response?.data?.message ?? 'An error occurred. Please try again.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6 max-w-md">
      <div className="space-y-5">
        <Field label="Current Password">
          <input
            name="current_password"
            type="password"
            value={form.current_password}
            onChange={handleChange}
            className={inputClass}
            placeholder="••••••••"
            autoComplete="current-password"
          />
        </Field>

        <Field label="New Password">
          <input
            name="new_password"
            type="password"
            value={form.new_password}
            onChange={handleChange}
            className={inputClass}
            placeholder="••••••••"
            autoComplete="new-password"
          />
        </Field>

        <Field label="Confirm New Password">
          <input
            name="new_password_confirmation"
            type="password"
            value={form.new_password_confirmation}
            onChange={handleChange}
            className={inputClass}
            placeholder="••••••••"
            autoComplete="new-password"
          />
        </Field>
      </div>

      {error && <p className="text-sm text-red-600">{error}</p>}
      {success && <p className="text-sm text-green-600">{success}</p>}

      <div>
        <button
          type="submit"
          disabled={submitting}
          className="inline-flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-60 transition-colors"
        >
          {submitting && <Spinner />}
          {submitting ? 'Updating...' : 'Update Password'}
        </button>
      </div>
    </form>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

const TABS = [
  { key: 'info',     label: 'Personal Info' },
  { key: 'security', label: 'Security' },
];

export default function ProfilePage() {
  const storeUser = useAuthStore((s) => s.user);
  const storeRole = useAuthStore((s) => s.role);
  const updateUser = useAuthStore((s) => s.updateUser);

  const [profile, setProfile] = useState(null);
  const [loadError, setLoadError] = useState('');
  const [activeTab, setActiveTab] = useState('info');
  const [uploading, setUploading] = useState(false);
  const [avatarError, setAvatarError] = useState('');

  // Crop modal state
  const [cropSrc, setCropSrc] = useState(null);
  const cropObjectUrl = useRef(null);

  const loadProfile = useCallback(async () => {
    setLoadError('');
    try {
      const data = await profileApi.getProfile();
      setProfile(data);
    } catch (err) {
      setLoadError(err?.response?.data?.message ?? 'Failed to load profile.');
    }
  }, []);

  useEffect(() => {
    loadProfile();
  }, [loadProfile]);

  // Step 1: user picks a file → open crop modal
  function handleAvatarSelect(file) {
    setAvatarError('');
    // Revoke previous object URL to avoid memory leaks
    if (cropObjectUrl.current) {
      URL.revokeObjectURL(cropObjectUrl.current);
    }
    const url = URL.createObjectURL(file);
    cropObjectUrl.current = url;
    setCropSrc(url);
  }

  // Step 2: user confirms crop → upload the blob
  async function handleCropConfirm(blob) {
    setUploading(true);
    try {
      const updated = await profileApi.uploadAvatar(blob);
      setProfile(prev => ({ ...prev, avatar_url: updated.avatar_url }));
      updateUser({ avatar_url: updated.avatar_url });
      closeCropModal();
    } catch (err) {
      setAvatarError(err?.response?.data?.message ?? 'Avatar upload failed.');
      // Keep modal open so the user can retry or cancel
    } finally {
      setUploading(false);
    }
  }

  function closeCropModal() {
    setCropSrc(null);
    if (cropObjectUrl.current) {
      URL.revokeObjectURL(cropObjectUrl.current);
      cropObjectUrl.current = null;
    }
  }

  function handleProfileSaved(updated) {
    setProfile(prev => ({ ...prev, ...updated }));
  }

  const displayName = profile?.name ?? storeUser?.name ?? '';
  const displayEmail = profile?.email ?? storeUser?.email ?? '';
  const avatarUrl = profile?.avatar_url ?? null;
  const role = storeRole ?? profile?.role ?? '';
  const badge = ROLE_BADGE[role] ?? { label: role, classes: 'bg-slate-100 text-slate-600' };

  return (
    <div className="space-y-6">
      {/* Page title */}
      <div>
        <h1 className="text-2xl font-semibold text-slate-800">Profile</h1>
        <p className="mt-0.5 text-sm text-slate-500">Manage your personal information and account security.</p>
      </div>

      {/* Load error */}
      {loadError && (
        <div className="rounded-xl bg-red-50 border border-red-200 px-5 py-4 flex items-start gap-3">
          <svg className="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
          </svg>
          <div>
            <p className="text-sm font-medium text-red-700">{loadError}</p>
            <button onClick={loadProfile} className="mt-1 text-xs text-red-600 underline hover:text-red-800">
              Try again
            </button>
          </div>
        </div>
      )}

      {/* Profile header card */}
      <div className="bg-white rounded-xl border border-slate-200 px-6 py-5">
        <div className="flex items-center gap-5">
          <Avatar
            name={displayName}
            avatarUrl={avatarUrl}
            onFileSelect={handleAvatarSelect}
            uploading={uploading}
          />
          <div className="min-w-0">
            <h2 className="text-xl font-bold text-slate-800 truncate">{displayName || '—'}</h2>
            <p className="text-sm text-slate-500 truncate">{displayEmail}</p>
            {role && (
              <span className={`mt-2 inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold ${badge.classes}`}>
                {badge.label}
              </span>
            )}
          </div>
        </div>
        {avatarError && <p className="mt-3 text-sm text-red-600">{avatarError}</p>}
      </div>

      {/* Avatar crop modal */}
      {cropSrc && (
        <AvatarCropModal
          imageSrc={cropSrc}
          onConfirm={handleCropConfirm}
          onCancel={closeCropModal}
          saving={uploading}
        />
      )}

      {/* Tabs + content card */}
      <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
        {/* Tab bar */}
        <div className="flex border-b border-slate-200">
          {TABS.map(tab => (
            <button
              key={tab.key}
              type="button"
              onClick={() => setActiveTab(tab.key)}
              className={[
                'px-6 py-3.5 text-sm font-medium transition-colors',
                activeTab === tab.key
                  ? 'text-blue-600 border-b-2 border-blue-600 -mb-px'
                  : 'text-slate-500 hover:text-slate-700',
              ].join(' ')}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {/* Tab content */}
        <div className="px-6 py-6">
          {activeTab === 'info' && (
            <PersonalInfoTab profile={profile} onProfileSaved={handleProfileSaved} />
          )}
          {activeTab === 'security' && <SecurityTab />}
        </div>
      </div>
    </div>
  );
}
