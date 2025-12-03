# Real-Time Notification System - Frontend Setup Guide

This guide provides complete frontend implementation for receiving real-time notifications using Laravel Echo and Pusher.

## Prerequisites

1. Install required packages:
```bash
npm install laravel-echo pusher-js

```

## React Implementation

### 1. Setup Laravel Echo (src/config/echo.js)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher available globally
window.Pusher = Pusher;

const echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.REACT_APP_PUSHER_APP_KEY,
    cluster: process.env.REACT_APP_PUSHER_APP_CLUSTER,
    forceTLS: true,
    encrypted: true,
    authEndpoint: `${process.env.REACT_APP_API_URL}/broadcasting/auth`,
    auth: {
        headers: {
            Authorization: `Bearer ${localStorage.getItem('token')}`,
            Accept: 'application/json',
        },
    },
});

export default echo;
```

### 2. React Hook for Notifications (src/hooks/useNotifications.js)

```javascript
import { useEffect, useState } from 'react';
import echo from '../config/echo';
import axios from 'axios';

const API_URL = process.env.REACT_APP_API_URL;

export const useNotifications = (user) => {
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);

    useEffect(() => {
        if (!user) return;

        // Fetch existing notifications
        const fetchNotifications = async () => {
            try {
                const response = await axios.get(`${API_URL}/api/notifications`, {
                    headers: {
                        Authorization: `Bearer ${localStorage.getItem('token')}`,
                    },
                });
                setNotifications(response.data.data || []);
                setUnreadCount(response.data.unread_count || 0);
            } catch (error) {
                console.error('Error fetching notifications:', error);
            }
        };

        fetchNotifications();

        // Determine channel based on user role
        let channelName;
        if (user.role === 'doctor') {
            const doctorId = user.doctor?.id || user.id;
            channelName = `doctor.${doctorId}`;
        } else if (user.role === 'patient') {
            const patientId = user.patient?.id || user.id;
            channelName = `patient.${patientId}`;
        } else {
            channelName = `user.${user.id}`;
        }

        // Subscribe to private channel
        const channel = echo.private(channelName);

        // Listen for consultation booked event
        channel.listen('.consultation.booked', (data) => {
            console.log('Consultation booked:', data);
            const notification = {
                id: Date.now(),
                type: 'consultation.booked',
                title: 'New Consultation Booked',
                message: data.message,
                data: data,
                read_at: null,
                created_at: new Date().toISOString(),
            };
            setNotifications((prev) => [notification, ...prev]);
            setUnreadCount((prev) => prev + 1);
        });

        // Listen for notification events (from database notifications)
        channel.listen('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', (data) => {
            console.log('Notification received:', data);
            const notification = {
                id: data.id || Date.now(),
                type: data.type,
                title: data.title || 'Notification',
                message: data.message || '',
                data: data,
                read_at: data.read_at,
                created_at: data.created_at || new Date().toISOString(),
            };
            setNotifications((prev) => [notification, ...prev]);
            if (!data.read_at) {
                setUnreadCount((prev) => prev + 1);
            }
        });

        // Cleanup on unmount
        return () => {
            echo.leave(channelName);
        };
    }, [user]);

    const markAsRead = async (notificationId) => {
        try {
            await axios.post(
                `${API_URL}/api/notifications/${notificationId}/read`,
                {},
                {
                    headers: {
                        Authorization: `Bearer ${localStorage.getItem('token')}`,
                    },
                }
            );
            setNotifications((prev) =>
                prev.map((n) =>
                    n.id === notificationId ? { ...n, read_at: new Date().toISOString() } : n
                )
            );
            setUnreadCount((prev) => Math.max(0, prev - 1));
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    };

    const markAllAsRead = async () => {
        try {
            await axios.post(
                `${API_URL}/api/notifications/read-all`,
                {},
                {
                    headers: {
                        Authorization: `Bearer ${localStorage.getItem('token')}`,
                    },
                }
            );
            setNotifications((prev) =>
                prev.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() }))
            );
            setUnreadCount(0);
        } catch (error) {
            console.error('Error marking all as read:', error);
        }
    };

    return {
        notifications,
        unreadCount,
        markAsRead,
        markAllAsRead,
    };
};
```

### 3. Notification Component (src/components/NotificationBell.jsx)

```javascript
import React, { useState, useRef, useEffect } from 'react';
import { useNotifications } from '../hooks/useNotifications';
import './NotificationBell.css';

const NotificationBell = ({ user }) => {
    const { notifications, unreadCount, markAsRead, markAllAsRead } = useNotifications(user);
    const [isOpen, setIsOpen] = useState(false);
    const dropdownRef = useRef(null);

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleNotificationClick = (notification) => {
        markAsRead(notification.id);
        // Handle navigation or action based on notification type
        if (notification.type === 'consultation.booked' || notification.data?.consultation_id) {
            // Navigate to consultation details
            window.location.href = `/consultations/${notification.data.consultation_id}`;
        }
    };

    const formatTime = (dateString) => {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);

        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes}m ago`;
        if (hours < 24) return `${hours}h ago`;
        if (days < 7) return `${days}d ago`;
        return date.toLocaleDateString();
    };

    return (
        <div className="notification-bell" ref={dropdownRef}>
            <button
                className="notification-bell-button"
                onClick={() => setIsOpen(!isOpen)}
                aria-label="Notifications"
            >
                <svg
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                >
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                    <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                </svg>
                {unreadCount > 0 && (
                    <span className="notification-badge">{unreadCount}</span>
                )}
            </button>

            {isOpen && (
                <div className="notification-dropdown">
                    <div className="notification-header">
                        <h3>Notifications</h3>
                        {unreadCount > 0 && (
                            <button onClick={markAllAsRead} className="mark-all-read">
                                Mark all as read
                            </button>
                        )}
                    </div>
                    <div className="notification-list">
                        {notifications.length === 0 ? (
                            <div className="notification-empty">No notifications</div>
                        ) : (
                            notifications.map((notification) => (
                                <div
                                    key={notification.id}
                                    className={`notification-item ${
                                        !notification.read_at ? 'unread' : ''
                                    }`}
                                    onClick={() => handleNotificationClick(notification)}
                                >
                                    <div className="notification-content">
                                        <div className="notification-title">
                                            {notification.title}
                                        </div>
                                        <div className="notification-message">
                                            {notification.message}
                                        </div>
                                        <div className="notification-time">
                                            {formatTime(notification.created_at)}
                                        </div>
                                    </div>
                                    {!notification.read_at && (
                                        <div className="notification-dot" />
                                    )}
                                </div>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
};

export default NotificationBell;
```

### 4. CSS for Notification Bell (src/components/NotificationBell.css)

```css
.notification-bell {
    position: relative;
    display: inline-block;
}

.notification-bell-button {
    position: relative;
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    color: #333;
    transition: color 0.2s;
}

.notification-bell-button:hover {
    color: #007bff;
}

.notification-badge {
    position: absolute;
    top: 4px;
    right: 4px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: bold;
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    width: 380px;
    max-height: 500px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    overflow: hidden;
}

.notification-header {
    padding: 16px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.mark-all-read {
    background: none;
    border: none;
    color: #007bff;
    cursor: pointer;
    font-size: 14px;
    padding: 4px 8px;
}

.mark-all-read:hover {
    text-decoration: underline;
}

.notification-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    transition: background 0.2s;
}

.notification-item:hover {
    background: #f8f9fa;
}

.notification-item.unread {
    background: #e7f3ff;
}

.notification-content {
    flex: 1;
}

.notification-title {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 4px;
    color: #333;
}

.notification-message {
    font-size: 13px;
    color: #666;
    margin-bottom: 4px;
}

.notification-time {
    font-size: 11px;
    color: #999;
}

.notification-dot {
    width: 8px;
    height: 8px;
    background: #007bff;
    border-radius: 50%;
    margin-left: 12px;
    margin-top: 6px;
}

.notification-empty {
    padding: 40px;
    text-align: center;
    color: #999;
}
```

### 5. Usage in App Component

```javascript
import React from 'react';
import NotificationBell from './components/NotificationBell';
import { useAuth } from './hooks/useAuth'; // Your auth hook

function App() {
    const { user } = useAuth();

    return (
        <div className="App">
            <header>
                <nav>
                    {/* Your navigation */}
                    {user && <NotificationBell user={user} />}
                </nav>
            </header>
            {/* Rest of your app */}
        </div>
    );
}
