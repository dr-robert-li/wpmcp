/**
 * SSE Transport for MCP Client
 * Handles Server-Sent Events communication with MCP servers
 */

import EventSource from 'eventsource';

export class SseTransport {
  /**
   * Create a new SSE transport
   * @param {Object} config - Configuration object
   * @param {string} config.url - The MCP server SSE URL
   * @param {Object} config.headers - Headers to include in requests
   */
  constructor(config) {
    this.url = config.url;
    this.headers = config.headers || {};
    this.eventSource = null;
    this.callbacks = {
      message: [],
      connection: [],
      ping: [],
      error: []
    };
  }

  /**
   * Initialize the SSE connection
   * @returns {Promise<Object>} - Connection result
   */
  async connect() {
    return new Promise((resolve, reject) => {
      try {
        // Create event source with authorization headers
        const eventSourceInit = {
          headers: this.headers
        };
        
        this.eventSource = new EventSource(this.url, eventSourceInit);
        
        // Handle connection opened
        this.eventSource.onopen = () => {
          console.log('SSE connection established');
          this._triggerCallbacks('connection', { status: 'connected' });
          resolve({ status: 'connected' });
        };
        
        // Handle general messages
        this.eventSource.onmessage = (event) => {
          try {
            const data = JSON.parse(event.data);
            this._triggerCallbacks('message', data);
          } catch (err) {
            console.error('Error parsing SSE message:', err);
          }
        };
        
        // Handle connection event
        this.eventSource.addEventListener('connection', (event) => {
          try {
            const data = JSON.parse(event.data);
            this._triggerCallbacks('connection', data);
          } catch (err) {
            console.error('Error parsing connection event:', err);
          }
        });
        
        // Handle ping events
        this.eventSource.addEventListener('ping', (event) => {
          try {
            const data = JSON.parse(event.data);
            this._triggerCallbacks('ping', data);
          } catch (err) {
            console.error('Error parsing ping event:', err);
          }
        });
        
        // Handle errors
        this.eventSource.onerror = (error) => {
          console.error('SSE connection error:', error);
          this._triggerCallbacks('error', error);
          
          // Try to reconnect if connection is closed
          if (this.eventSource.readyState === EventSource.CLOSED) {
            console.log('Attempting to reconnect SSE...');
          }
        };
      } catch (error) {
        console.error('Error setting up SSE connection:', error);
        reject(error);
      }
    });
  }
  
  /**
   * Add an event listener for SSE events
   * @param {string} event - Event type to listen for
   * @param {Function} callback - Callback function
   */
  addEventListener(event, callback) {
    if (this.callbacks[event]) {
      this.callbacks[event].push(callback);
    } else {
      console.warn(`Unknown event type: ${event}`);
    }
  }
  
  /**
   * Remove an event listener
   * @param {string} event - Event type
   * @param {Function} callback - Callback to remove
   */
  removeEventListener(event, callback) {
    if (this.callbacks[event]) {
      this.callbacks[event] = this.callbacks[event].filter(cb => cb !== callback);
    }
  }
  
  /**
   * Close the SSE connection
   */
  close() {
    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
      console.log('SSE connection closed');
    }
  }
  
  /**
   * Trigger all callbacks for a given event
   * @param {string} event - Event type
   * @param {Object} data - Event data
   * @private
   */
  _triggerCallbacks(event, data) {
    if (this.callbacks[event]) {
      this.callbacks[event].forEach(callback => {
        try {
          callback(data);
        } catch (err) {
          console.error('Error in SSE callback:', err);
        }
      });
    }
  }
}

export default SseTransport;