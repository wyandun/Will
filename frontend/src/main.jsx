import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './index.css';
// Importing the store here ensures the axios token getter is registered
// before any component mounts and makes API calls.
import './store/authStore';
import './i18n';
import App from './App.jsx';

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>
);
