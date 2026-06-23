import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    scenarios: {
        load_test: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 5000 },   
                { duration: '30s', target: 10000 },  
                { duration: '5m', target: 10000 },   
                { duration: '30s', target: 0 },      
            ],
            gracefulRampDown: '30s',
        },
    },
    discardResponseBodies: true, 
    thresholds: {
        http_req_duration: ['p(95)<30000'], 
        http_req_failed: ['rate<0.05'],
    },
};

const BASE_URL = 'http://localhost:8000/api/v1/catalog/products';

export default function () {
    const params = {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        timeout: '300s', 
    };

    const res = http.get(`${BASE_URL}?per_page=15`, params);

    check(res, {
        'status is 200': (r) => r.status === 200,
    });
    
    sleep(5);
}
